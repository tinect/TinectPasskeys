(()=>{var k=Object.defineProperty;var o=(e,t,s)=>()=>{if(s)throw s[0];try{return e&&(t=e(e=0)),t}catch(a){throw s=[a],a}};var r=(e,t)=>{for(var s in t)k(e,s,{get:t[s],enumerable:!0})};var m,d=o(()=>{m=`{% block tinect_passkey_customer_card %}
<mt-card
    class="tinect-passkey-customer-card"
    position-identifier="tinect-passkey-customer-card"
    :title="$t('tinect-passkeys.customerCard.title')"
    :is-loading="isLoading"
>
    <mt-banner
        v-if="passwordLoginDisabled"
        class="tinect-passkey-customer-card__password-login"
        variant="info"
        :closable="false"
    >
        {{ $t('tinect-passkeys.customerCard.passwordLoginDisabled') }}
        <mt-button
            v-if="acl.can('customer.editor')"
            class="tinect-passkey-customer-card__enable-password-login"
            size="small"
            variant="secondary"
            @click="showEnablePasswordLoginModal = true"
        >
            {{ $t('tinect-passkeys.customerCard.actionEnablePasswordLogin') }}
        </mt-button>
    </mt-banner>
    <template
        v-if="passkeys.length > 0"
        #grid
    >
        <sw-data-grid
            :data-source="passkeys"
            :columns="columns"
            :show-selection="false"
            :show-actions="acl.can('customer.editor')"
            :plain-appearance="true"
        >
            <template #column-createdAt="{ item }">
                {{ formatDate(item.createdAt) }}
            </template>

            <template #column-lastUsedAt="{ item }">
                {{ item.lastUsedAt ? formatDate(item.lastUsedAt) : $t('tinect-passkeys.customerCard.neverUsed') }}
            </template>

            <template #actions="{ item }">
                <sw-context-menu-item
                    variant="danger"
                    @click="passkeyToDelete = item"
                >
                    {{ $t('tinect-passkeys.customerCard.actionDelete') }}
                </sw-context-menu-item>
            </template>
        </sw-data-grid>
        <sw-modal
            v-if="passkeyToDelete"
            :title="$t('global.default.warning')"
            variant="small"
            @modal-close="passkeyToDelete = null"
        >
            <p>
                {{ $t('tinect-passkeys.customerCard.deleteConfirm', { name: passkeyToDelete.name }) }}
            </p>
            <template #modal-footer>
                <mt-button
                    size="small"
                    variant="secondary"
                    @click="passkeyToDelete = null"
                >
                    {{ $t('global.default.cancel') }}
                </mt-button>
                <mt-button
                    size="small"
                    variant="critical"
                    @click="deletePasskey(passkeyToDelete)"
                >
                    {{ $t('global.default.delete') }}
                </mt-button>
            </template>
        </sw-modal>
    </template>
    <p v-else-if="!isLoading">
        {{ $t('tinect-passkeys.customerCard.empty') }}
    </p>
    <sw-modal
        v-if="showEnablePasswordLoginModal"
        :title="$t('global.default.warning')"
        variant="small"
        @modal-close="showEnablePasswordLoginModal = false"
    >
        <p>
            {{ $t('tinect-passkeys.customerCard.enablePasswordLoginConfirm') }}
        </p>
        <template #modal-footer>
            <mt-button
                size="small"
                variant="secondary"
                @click="showEnablePasswordLoginModal = false"
            >
                {{ $t('global.default.cancel') }}
            </mt-button>
            <mt-button
                size="small"
                variant="primary"
                @click="enablePasswordLogin"
            >
                {{ $t('tinect-passkeys.customerCard.actionEnablePasswordLogin') }}
            </mt-button>
        </template>
    </sw-modal>
</mt-card>
{% endblock %}`});var p={};r(p,{default:()=>L});var L,u=o(()=>{d();L={template:m,inject:["acl","tinectPasskeyApiService"],mixins:[Shopware.Mixin.getByName("notification")],props:{customerId:{type:String,required:!0}},data(){return{passkeys:[],passwordLoginDisabled:!1,isLoading:!1,passkeyToDelete:null,showEnablePasswordLoginModal:!1}},computed:{columns(){return[{property:"name",label:this.$t("tinect-passkeys.customerCard.columnName"),primary:!0},{property:"createdAt",label:this.$t("tinect-passkeys.customerCard.columnCreatedAt")},{property:"lastUsedAt",label:this.$t("tinect-passkeys.customerCard.columnLastUsedAt")}]},dateFilter(){return Shopware.Filter.getByName("date")}},watch:{customerId:{immediate:!0,handler(){this.loadPasskeys()}}},methods:{async loadPasskeys(){if(this.acl.can("customer.viewer")){this.isLoading=!0;try{let e=await this.tinectPasskeyApiService.getCustomerPasskeys(this.customerId);this.passkeys=e.passkeys,this.passwordLoginDisabled=e.passwordLoginDisabled}finally{this.isLoading=!1}}},async enablePasswordLogin(){this.showEnablePasswordLoginModal=!1,this.isLoading=!0;try{await this.tinectPasskeyApiService.enableCustomerPasswordLogin(this.customerId),this.createNotificationSuccess({message:this.$t("tinect-passkeys.customerCard.notificationPasswordLoginEnabled")})}catch{this.createNotificationError({message:this.$t("tinect-passkeys.customerCard.notificationPasswordLoginError")})}finally{await this.loadPasskeys()}},async deletePasskey(e){this.isLoading=!0;try{await this.tinectPasskeyApiService.deleteCustomerPasskey(this.customerId,e.id),this.createNotificationSuccess({message:this.$t("tinect-passkeys.customerCard.notificationDeleteSuccess")})}catch{this.createNotificationError({message:this.$t("tinect-passkeys.customerCard.notificationDeleteError")})}finally{this.passkeyToDelete=null,await this.loadPasskeys()}},formatDate(e){return e?this.dateFilter(`${e.replace(" ","T")}Z`):"-"}}}});var y,h=o(()=>{y=`{% block sw_customer_detail_base_default_addresses_card %}
{% parent() %}

<tinect-passkey-customer-card
    v-if="customer?.id && !customer.guest"
    :customer-id="customer.id"
/>
{% endblock %}`});var g={};r(g,{default:()=>v});var v,w=o(()=>{h();v={template:y}});var l={"tinect-passkeys":{customerCard:{title:"Passkeys",columnName:"Bezeichnung",columnCreatedAt:"Erstellt",columnLastUsedAt:"Zuletzt verwendet",neverUsed:"Noch nie",empty:"Der Kunde hat keine Passkeys hinterlegt.",actionDelete:"Entfernen",deleteConfirm:"Passkey \u201E{name}\u201C wirklich entfernen? Der Kunde kann sich damit nicht mehr anmelden.",notificationDeleteSuccess:"Der Passkey wurde entfernt.",notificationDeleteError:"Der Passkey konnte nicht entfernt werden.",passwordLoginDisabled:"Der Kunde hat die Anmeldung per Passwort deaktiviert und meldet sich nur noch mit Passkey an.",actionEnablePasswordLogin:"Passwort-Login wieder aktivieren",notificationPasswordLoginEnabled:"Die Anmeldung per Passwort ist wieder aktiviert.",notificationPasswordLoginError:"Die Anmeldung per Passwort konnte nicht aktiviert werden.",enablePasswordLoginConfirm:"Die Anmeldung per Passwort f\xFCr diesen Kunden wieder aktivieren? Der Kunde wird per Mail dar\xFCber informiert."}}};var c={"tinect-passkeys":{customerCard:{title:"Passkeys",columnName:"Name",columnCreatedAt:"Created",columnLastUsedAt:"Last used",neverUsed:"Never",empty:"This customer has no passkeys.",actionDelete:"Remove",deleteConfirm:'Really remove passkey "{name}"? The customer can no longer log in with it.',notificationDeleteSuccess:"The passkey has been removed.",notificationDeleteError:"The passkey could not be removed.",passwordLoginDisabled:"The customer disabled the password login and only logs in with passkeys.",actionEnablePasswordLogin:"Enable password login again",notificationPasswordLoginEnabled:"The password login has been enabled again.",notificationPasswordLoginError:"The password login could not be enabled.",enablePasswordLoginConfirm:"Enable the password login for this customer again? The customer is informed by e-mail."}}};var{ApiService:n}=Shopware.Classes,i=class extends n{constructor(t,s,a="_action/tinect-passkeys"){super(t,s,a)}getCustomerPasskeys(t){return this.httpClient.get(`${this.getApiBasePath()}/customer/${t}`,{headers:this.getBasicHeaders()}).then(s=>n.handleResponse(s))}enableCustomerPasswordLogin(t){return this.httpClient.post(`${this.getApiBasePath()}/customer/${t}/enable-password-login`,{},{headers:this.getBasicHeaders()}).then(s=>n.handleResponse(s))}deleteCustomerPasskey(t,s){return this.httpClient.delete(`${this.getApiBasePath()}/customer/${t}/${s}`,{headers:this.getBasicHeaders()}).then(a=>n.handleResponse(a))}};Shopware.Application.addServiceProvider("tinectPasskeyApiService",e=>{let t=Shopware.Application.getContainer("init");return new i(t.httpClient,e.loginService)});Shopware.Component.register("tinect-passkey-customer-card",()=>Promise.resolve().then(()=>(u(),p)));Shopware.Component.override("sw-customer-detail-base",()=>Promise.resolve().then(()=>(w(),g)));Shopware.Locale.extend("de-DE",l);Shopware.Locale.extend("en-GB",c);})();
