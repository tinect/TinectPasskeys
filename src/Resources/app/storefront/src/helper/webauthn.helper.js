function base64UrlToBuffer(value) {
    const base64 = value.replace(/-/g, '+').replace(/_/g, '/');
    const padded = base64.padEnd(
        base64.length + ((4 - (base64.length % 4)) % 4),
        '='
    );
    const binary = window.atob(padded);

    return Uint8Array.from(binary, (char) => char.charCodeAt(0)).buffer;
}

function bufferToBase64Url(buffer) {
    const binary = String.fromCharCode(...new Uint8Array(buffer));

    return window
        .btoa(binary)
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=+$/, '');
}

function decodeDescriptors(descriptors = []) {
    return descriptors.map((descriptor) => ({
        ...descriptor,
        id: base64UrlToBuffer(descriptor.id),
    }));
}

export function isPasskeySupported() {
    return (
        typeof window.PublicKeyCredential === 'function' &&
        !!navigator.credentials
    );
}

export async function isConditionalMediationAvailable() {
    if (
        !isPasskeySupported() ||
        typeof PublicKeyCredential.isConditionalMediationAvailable !==
            'function'
    ) {
        return false;
    }

    return PublicKeyCredential.isConditionalMediationAvailable();
}

export function parseCreationOptions(options) {
    if (
        typeof PublicKeyCredential.parseCreationOptionsFromJSON === 'function'
    ) {
        return PublicKeyCredential.parseCreationOptionsFromJSON(options);
    }

    return {
        ...options,
        challenge: base64UrlToBuffer(options.challenge),
        user: { ...options.user, id: base64UrlToBuffer(options.user.id) },
        excludeCredentials: decodeDescriptors(options.excludeCredentials),
    };
}

export function parseRequestOptions(options) {
    if (typeof PublicKeyCredential.parseRequestOptionsFromJSON === 'function') {
        return PublicKeyCredential.parseRequestOptionsFromJSON(options);
    }

    return {
        ...options,
        challenge: base64UrlToBuffer(options.challenge),
        allowCredentials: decodeDescriptors(options.allowCredentials),
    };
}

export function serializeCredential(credential) {
    if (typeof credential.toJSON === 'function') {
        return JSON.stringify(credential.toJSON());
    }

    const response = {
        clientDataJSON: bufferToBase64Url(credential.response.clientDataJSON),
    };

    if (credential.response.attestationObject) {
        response.attestationObject = bufferToBase64Url(
            credential.response.attestationObject
        );
        response.transports = credential.response.getTransports?.() ?? [];
    }

    if (credential.response.authenticatorData) {
        response.authenticatorData = bufferToBase64Url(
            credential.response.authenticatorData
        );
        response.signature = bufferToBase64Url(credential.response.signature);

        if (credential.response.userHandle) {
            response.userHandle = bufferToBase64Url(
                credential.response.userHandle
            );
        }
    }

    return JSON.stringify({
        id: credential.id,
        rawId: bufferToBase64Url(credential.rawId),
        type: credential.type,
        authenticatorAttachment: credential.authenticatorAttachment ?? null,
        clientExtensionResults: credential.getClientExtensionResults(),
        response,
    });
}

export async function postForm(url, data) {
    const body = new FormData();
    Object.entries(data).forEach(([key, value]) =>
        body.append(key, value ?? '')
    );

    const response = await fetch(url, {
        method: 'POST',
        body,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });

    const json = await response.json().catch(() => ({}));

    if (!response.ok) {
        const error = new Error(json.message || response.statusText);
        error.serverMessage = json.message;
        throw error;
    }

    return json;
}

export function isAbortError(error) {
    return error?.name === 'NotAllowedError' || error?.name === 'AbortError';
}
