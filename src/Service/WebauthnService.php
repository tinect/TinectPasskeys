<?php declare(strict_types=1);

namespace Tinect\Passkeys\Service;

use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

class WebauthnService
{
    private const SESSION_CREATION_OPTIONS = 'tinect_passkeys.creation_options';
    private const SESSION_REQUEST_OPTIONS = 'tinect_passkeys.request_options';
    private const TIMEOUT = 300_000;
    private const ALGORITHM_ES256 = -7;
    private const ALGORITHM_RS256 = -257;

    private ?SerializerInterface $serializer = null;

    /**
     * @param list<string> $existingCredentials JSON encoded credential records of the customer
     */
    public function createCreationOptions(
        Request $request,
        string $customerId,
        string $email,
        string $displayName,
        string $rpName,
        array $existingCredentials,
    ): string {
        $options = PublicKeyCredentialCreationOptions::create(
            PublicKeyCredentialRpEntity::create($rpName, $request->getHost()),
            PublicKeyCredentialUserEntity::create($email, Uuid::fromHexToBytes($customerId), $displayName),
            random_bytes(32),
            pubKeyCredParams: [
                PublicKeyCredentialParameters::createPk(self::ALGORITHM_ES256),
                PublicKeyCredentialParameters::createPk(self::ALGORITHM_RS256),
            ],
            authenticatorSelection: AuthenticatorSelectionCriteria::create(
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
                residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
            ),
            attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            excludeCredentials: array_map(
                fn (string $credential): PublicKeyCredentialDescriptor => $this->deserializeCredentialRecord($credential)->getPublicKeyCredentialDescriptor(),
                $existingCredentials
            ),
            timeout: self::TIMEOUT,
        );

        return $this->storeOptions($request, self::SESSION_CREATION_OPTIONS, $options);
    }

    /**
     * @throws \Throwable when the response could not be verified
     */
    public function verifyCreation(Request $request, string $credentialJson, string $customerId): CredentialRecord
    {
        $options = $this->popOptions($request, self::SESSION_CREATION_OPTIONS, PublicKeyCredentialCreationOptions::class);

        if (!hash_equals($options->user->id, Uuid::fromHexToBytes($customerId))) {
            throw new \RuntimeException('Passkey challenge belongs to another customer');
        }
        $response = $this->deserializePublicKeyCredential($credentialJson)->response;

        if (!$response instanceof AuthenticatorAttestationResponse) {
            throw new \InvalidArgumentException('Invalid attestation response');
        }

        return AuthenticatorAttestationResponseValidator::create($this->createCeremonyStepManagerFactory($request)->creationCeremony())
            ->check($response, $options, $request->getHost());
    }

    public function createRequestOptions(Request $request): string
    {
        $options = PublicKeyCredentialRequestOptions::create(
            random_bytes(32),
            rpId: $request->getHost(),
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            timeout: self::TIMEOUT,
        );

        return $this->storeOptions($request, self::SESSION_REQUEST_OPTIONS, $options);
    }

    /**
     * Returns the raw credential id of the given public key credential, so the stored credential record can be looked up.
     */
    public function getAssertionCredentialId(string $credentialJson): string
    {
        return $this->deserializePublicKeyCredential($credentialJson)->rawId;
    }

    /**
     * @throws \Throwable when the response could not be verified
     */
    public function verifyAssertion(Request $request, string $credentialJson, string $storedCredential, string $customerId): CredentialRecord
    {
        $options = $this->popOptions($request, self::SESSION_REQUEST_OPTIONS, PublicKeyCredentialRequestOptions::class);
        $response = $this->deserializePublicKeyCredential($credentialJson)->response;

        if (!$response instanceof AuthenticatorAssertionResponse) {
            throw new \InvalidArgumentException('Invalid assertion response');
        }

        return AuthenticatorAssertionResponseValidator::create($this->createCeremonyStepManagerFactory($request)->requestCeremony())
            ->check(
                $this->deserializeCredentialRecord($storedCredential),
                $response,
                $options,
                $request->getHost(),
                Uuid::fromHexToBytes($customerId)
            );
    }

    public function serialize(object $data): string
    {
        return $this->getSerializer()->serialize($data, 'json', [
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            JsonEncode::OPTIONS => \JSON_THROW_ON_ERROR,
        ]);
    }

    public function deserializeCredentialRecord(string $json): CredentialRecord
    {
        return $this->getSerializer()->deserialize($json, CredentialRecord::class, 'json');
    }

    private function deserializePublicKeyCredential(string $json): PublicKeyCredential
    {
        return $this->getSerializer()->deserialize($json, PublicKeyCredential::class, 'json');
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function popOptions(Request $request, string $key, string $class): object
    {
        $session = $request->getSession();
        $pending = $session->get($key);
        $session->remove($key);

        if (!\is_array($pending) || !\is_string($pending['options'] ?? null) || !\is_int($pending['createdAt'] ?? null)) {
            throw new \RuntimeException('No pending passkey challenge');
        }

        if (time() - $pending['createdAt'] > intdiv(self::TIMEOUT, 1000)) {
            throw new \RuntimeException('Passkey challenge expired');
        }

        return $this->getSerializer()->deserialize($pending['options'], $class, 'json');
    }

    private function storeOptions(Request $request, string $key, PublicKeyCredentialCreationOptions|PublicKeyCredentialRequestOptions $options): string
    {
        $json = $this->serialize($options);
        $request->getSession()->set($key, ['options' => $json, 'createdAt' => time()]);

        return $json;
    }

    private function createCeremonyStepManagerFactory(Request $request): CeremonyStepManagerFactory
    {
        $factory = new CeremonyStepManagerFactory();
        $factory->setAllowedOrigins([$request->getSchemeAndHttpHost()]);

        return $factory;
    }

    private function getSerializer(): SerializerInterface
    {
        return $this->serializer ??= (new WebauthnSerializerFactory(AttestationStatementSupportManager::create()))->create();
    }
}
