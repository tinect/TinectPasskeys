const { ApiService } = Shopware.Classes;

export default class TinectPasskeyApiService extends ApiService {
    constructor(
        httpClient,
        loginService,
        apiEndpoint = '_action/tinect-passkeys'
    ) {
        super(httpClient, loginService, apiEndpoint);
    }

    getCustomerPasskeys(customerId) {
        return this.httpClient
            .get(`${this.getApiBasePath()}/customer/${customerId}`, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse(response));
    }

    enableCustomerPasswordLogin(customerId) {
        return this.httpClient
            .post(
                `${this.getApiBasePath()}/customer/${customerId}/enable-password-login`,
                {},
                {
                    headers: this.getBasicHeaders(),
                }
            )
            .then((response) => ApiService.handleResponse(response));
    }

    deleteCustomerPasskey(customerId, passkeyId) {
        return this.httpClient
            .delete(
                `${this.getApiBasePath()}/customer/${customerId}/${passkeyId}`,
                {
                    headers: this.getBasicHeaders(),
                }
            )
            .then((response) => ApiService.handleResponse(response));
    }

    getDashboardSummary(params = {}) {
        return this.getDashboard('summary', params);
    }

    getDashboardRecent(params = {}) {
        return this.getDashboard('recent', params);
    }

    getDashboardAddedPerDay(params = {}) {
        return this.getDashboard('added-per-day', params);
    }

    getDashboard(path, params) {
        return this.httpClient
            .get(`${this.getApiBasePath()}/dashboard/${path}`, {
                params: Object.fromEntries(
                    Object.entries(params).filter(
                        ([, value]) => value !== null && value !== undefined
                    )
                ),
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse(response));
    }
}
