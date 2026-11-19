(()=>{var U=Object.defineProperty;var i=(e,t,s)=>()=>{if(s)throw s[0];try{return e&&(t=e(e=0)),t}catch(a){throw s=[a],a}};var r=(e,t)=>{for(var s in t)U(e,s,{get:t[s],enumerable:!0})};var h,u=i(()=>{h=`{% block tinect_passkeys_widget_summary %}
<div class="tinect-passkeys-widget-summary">
    <sw-loader v-if="isLoading" />
    <p
        v-else-if="hasError"
        class="tinect-passkeys-widget-summary__empty"
    >
        {{ $t('tinect-passkeys.dashboard.loadError') }}
    </p>
    <dl
        v-else-if="summary"
        class="tinect-passkeys-widget-summary__tiles"
    >
        <div
            v-for="tile in tiles"
            :key="tile.key"
            class="tinect-passkeys-widget-summary__tile"
        >
            <dt class="tinect-passkeys-widget-summary__label">
                {{ tile.label }}
            </dt>
            <dd class="tinect-passkeys-widget-summary__value">
                {{ tile.value }}
            </dd>
            <dd
                v-if="tile.hint"
                class="tinect-passkeys-widget-summary__hint"
            >
                {{ tile.hint }}
            </dd>
        </div>
    </dl>
</div>
{% endblock %}
`});var y=i(()=>{});var g={};r(g,{default:()=>j});var j,k=i(()=>{u();y();j={template:h,inject:["acl","tinectPasskeyApiService"],props:{settings:{type:Object,required:!1,default:()=>({})}},data(){return{summary:null,isLoading:!0,hasError:!1}},computed:{salesChannelId(){return this.settings.salesChannelId??null},adoptionRate(){return this.summary?.customers?this.summary.customersWithPasskey/this.summary.customers:0},tiles(){return this.summary?[{key:"customersWithPasskey",value:this.formatNumber(this.summary.customersWithPasskey),label:this.$t("tinect-passkeys.dashboard.summary.customersWithPasskey"),hint:this.$t("tinect-passkeys.dashboard.summary.adoptionRate",{rate:this.formatPercent(this.adoptionRate),customers:this.formatNumber(this.summary.customers)})},{key:"passkeys",value:this.formatNumber(this.summary.passkeys),label:this.$t("tinect-passkeys.dashboard.summary.passkeys")},{key:"activeCustomers",value:this.formatNumber(this.summary.activeCustomers),label:this.$t("tinect-passkeys.dashboard.summary.activeCustomers",{days:this.summary.activeDays})},{key:"passwordLoginDisabled",value:this.formatNumber(this.summary.passwordLoginDisabled),label:this.$t("tinect-passkeys.dashboard.summary.passwordLoginDisabled")}]:[]}},watch:{salesChannelId:{immediate:!0,handler(){this.load()}}},methods:{async load(){if(!this.acl.can("customer.viewer")){this.isLoading=!1;return}this.isLoading=!0,this.hasError=!1;try{this.summary=await this.tinectPasskeyApiService.getDashboardSummary({salesChannelId:this.salesChannelId})}catch{this.summary=null,this.hasError=!0}finally{this.isLoading=!1}},formatNumber(e){return new Intl.NumberFormat().format(e)},formatPercent(e){return new Intl.NumberFormat(void 0,{style:"percent",maximumFractionDigits:1}).format(e)}}}});var w,f=i(()=>{w=`{% block tinect_passkeys_widget_added %}
<div class="tinect-passkeys-widget-added">
    <div class="tinect-passkeys-widget-added__total">
        {{ formattedTotal }}
    </div>
    <sw-chart-card
        class="tinect-passkeys-widget-added__chart"
        :available-ranges="availableRanges"
        :default-range-index="defaultRangeIndex"
        :series="chartSeries"
        :options="chartOptions"
        :is-loading="isLoading"
        type="bar"
        fill-empty-values="day"
        sort
        position-identifier="tinect-passkeys-widget-added"
        @sw-chart-card-range-update="onRangeUpdate"
    >
        <template #range-option="{ range }">
            {{ $t(\`tinect-passkeys.dashboard.range.\${range}\`) }}
        </template>
    </sw-chart-card>
</div>
{% endblock %}
`});var b=i(()=>{});var v={};r(v,{default:()=>M});var d,H,M,D=i(()=>{f();b();d={"7Days":7,"30Days":30,"90Days":90,"180Days":180,"360Days":360},H="30Days",M={template:w,inject:["acl","tinectPasskeyApiService"],emits:["update-settings"],props:{settings:{type:Object,required:!1,default:()=>({})}},data(){return{series:[],selectedRange:Object.hasOwn(d,this.settings.range)?this.settings.range:H,fromDate:null,isLoading:!0}},computed:{salesChannelId(){return this.settings.salesChannelId??null},availableRanges(){return Object.keys(d)},defaultRangeIndex(){return Math.max(0,this.availableRanges.indexOf(this.selectedRange))},total(){return this.series.reduce((e,t)=>e+t.y,0)},formattedTotal(){return new Intl.NumberFormat().format(this.total)},chartSeries(){return[{name:this.$t("tinect-passkeys.dashboard.added.seriesName"),data:this.series}]},chartOptions(){return{dataLabels:{enabled:!1},xaxis:{type:"datetime",min:this.fromDate?.getTime(),labels:{datetimeUTC:!1}},yaxis:{min:0,forceNiceScale:!0,labels:{formatter:e=>Math.round(e).toString()}}}}},watch:{salesChannelId:{immediate:!0,handler(){this.load()}}},methods:{async load(){if(!this.acl.can("customer.viewer")){this.isLoading=!1;return}let e=d[this.selectedRange],t=new Date;t.setHours(0,0,0,0),t.setDate(t.getDate()-e),this.isLoading=!0,this.fromDate=t;try{let s=await this.tinectPasskeyApiService.getDashboardAddedPerDay({days:e,salesChannelId:this.salesChannelId,timeZone:this.timeZone()});this.series=s.days.map(a=>({x:Date.parse(`${a.date}T00:00:00`),y:a.count}))}catch{this.series=[]}finally{this.isLoading=!1}},timeZone(){return Shopware.Store.get("session").currentUser?.timeZone??Intl.DateTimeFormat().resolvedOptions().timeZone},onRangeUpdate(e){Object.hasOwn(d,e)&&(this.selectedRange=e,this.$emit("update-settings",{range:e}),this.load())}}}});var C,P=i(()=>{C=`{% block tinect_passkeys_widget_recent %}
<div class="tinect-passkeys-widget-recent">
    <p
        v-if="hasError"
        class="tinect-passkeys-widget-recent__empty"
    >
        {{ $t('tinect-passkeys.dashboard.loadError') }}
    </p>
    <p
        v-else-if="!isLoading && passkeys.length === 0"
        class="tinect-passkeys-widget-recent__empty"
    >
        {{ $t('tinect-passkeys.dashboard.recent.empty') }}
    </p>
    <sw-data-grid
        v-else
        class="tinect-passkeys-widget-recent__grid"
        :data-source="passkeys"
        :columns="columns"
        :is-loading="isLoading"
        :show-selection="false"
        :show-actions="false"
        :plain-appearance="true"
        :allow-inline-edit="false"
        :allow-column-edit="false"
    >
        <template #column-customer="{ item }">
            <router-link :to="{ name: 'sw.customer.detail', params: { id: item.customerId } }">
                {{ item.lastName }}, {{ item.firstName }}
            </router-link>
            <div class="tinect-passkeys-widget-recent__customer-number">
                {{ item.customerNumber }}
            </div>
        </template>

        <template #column-createdAt="{ item }">
            {{ formatDate(item.createdAt) }}
        </template>

        <template #column-lastUsedAt="{ item }">
            {{ item.lastUsedAt ? formatDate(item.lastUsedAt) : $t('tinect-passkeys.customerCard.neverUsed') }}
        </template>
    </sw-data-grid>
</div>
{% endblock %}
`});var L=i(()=>{});var _={};r(_,{default:()=>Z});var Z,x=i(()=>{P();L();Z={template:C,inject:["acl","tinectPasskeyApiService"],props:{settings:{type:Object,required:!1,default:()=>({})}},data(){return{passkeys:[],isLoading:!0,hasError:!1}},computed:{salesChannelId(){return this.settings.salesChannelId??null},limit(){return this.settings.limit??10},params(){return{salesChannelId:this.salesChannelId,limit:this.limit}},columns(){return[{property:"customer",label:this.$t("tinect-passkeys.dashboard.recent.columnCustomer"),primary:!0},{property:"name",label:this.$t("tinect-passkeys.customerCard.columnName")},{property:"createdAt",label:this.$t("tinect-passkeys.customerCard.columnCreatedAt")},{property:"lastUsedAt",label:this.$t("tinect-passkeys.customerCard.columnLastUsedAt")}]},dateFilter(){return Shopware.Filter.getByName("date")}},watch:{params:{immediate:!0,handler(){this.load()}}},methods:{async load(){if(!this.acl.can("customer.viewer")){this.isLoading=!1;return}this.isLoading=!0,this.hasError=!1;try{let e=await this.tinectPasskeyApiService.getDashboardRecent(this.params);this.passkeys=e.passkeys}catch{this.passkeys=[],this.hasError=!0}finally{this.isLoading=!1}},formatDate(e){return e?this.dateFilter(`${e.replace(" ","T")}Z`):"-"}}}});var $,S=i(()=>{$=`{% block tinect_passkey_customer_card %}
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
    <!-- no v-else: the grid above is a slot, so it is no sibling for the compiler -->
    <p v-if="passkeys.length === 0 && !isLoading">
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
{% endblock %}`});var E={};r(E,{default:()=>V});var V,N=i(()=>{S();V={template:$,inject:["acl","tinectPasskeyApiService"],mixins:[Shopware.Mixin.getByName("notification")],props:{customerId:{type:String,required:!0}},data(){return{passkeys:[],passwordLoginDisabled:!1,isLoading:!1,passkeyToDelete:null,showEnablePasswordLoginModal:!1}},computed:{columns(){return[{property:"name",label:this.$t("tinect-passkeys.customerCard.columnName"),primary:!0},{property:"createdAt",label:this.$t("tinect-passkeys.customerCard.columnCreatedAt")},{property:"lastUsedAt",label:this.$t("tinect-passkeys.customerCard.columnLastUsedAt")}]},dateFilter(){return Shopware.Filter.getByName("date")}},watch:{customerId:{immediate:!0,handler(){this.loadPasskeys()}}},methods:{async loadPasskeys(){if(this.acl.can("customer.viewer")){this.isLoading=!0;try{let e=await this.tinectPasskeyApiService.getCustomerPasskeys(this.customerId);this.passkeys=e.passkeys,this.passwordLoginDisabled=e.passwordLoginDisabled}finally{this.isLoading=!1}}},async enablePasswordLogin(){this.showEnablePasswordLoginModal=!1,this.isLoading=!0;try{await this.tinectPasskeyApiService.enableCustomerPasswordLogin(this.customerId),this.createNotificationSuccess({message:this.$t("tinect-passkeys.customerCard.notificationPasswordLoginEnabled")})}catch{this.createNotificationError({message:this.$t("tinect-passkeys.customerCard.notificationPasswordLoginError")})}finally{await this.loadPasskeys()}},async deletePasskey(e){this.isLoading=!0;try{await this.tinectPasskeyApiService.deleteCustomerPasskey(this.customerId,e.id),this.createNotificationSuccess({message:this.$t("tinect-passkeys.customerCard.notificationDeleteSuccess")})}catch{this.createNotificationError({message:this.$t("tinect-passkeys.customerCard.notificationDeleteError")})}finally{this.passkeyToDelete=null,await this.loadPasskeys()}},formatDate(e){return e?this.dateFilter(`${e.replace(" ","T")}Z`):"-"}}}});var T,z=i(()=>{T=`{% block sw_customer_detail_base_default_addresses_card %}
{% parent() %}

<tinect-passkey-customer-card
    v-if="customer?.id && !customer.guest"
    :customer-id="customer.id"
/>
{% endblock %}`});var I={};r(I,{default:()=>Q});var Q,R=i(()=>{z();Q={template:T}});var m={"tinect-passkeys":{customerCard:{title:"Passkeys",columnName:"Bezeichnung",columnCreatedAt:"Erstellt",columnLastUsedAt:"Zuletzt verwendet",neverUsed:"Noch nie",empty:"Der Kunde hat keine Passkeys hinterlegt.",actionDelete:"Entfernen",deleteConfirm:"Passkey \u201E{name}\u201C wirklich entfernen? Der Kunde kann sich damit nicht mehr anmelden.",notificationDeleteSuccess:"Der Passkey wurde entfernt.",notificationDeleteError:"Der Passkey konnte nicht entfernt werden.",passwordLoginDisabled:"Der Kunde hat die Anmeldung per Passwort deaktiviert und meldet sich nur noch mit Passkey an.",actionEnablePasswordLogin:"Passwort-Login wieder aktivieren",notificationPasswordLoginEnabled:"Die Anmeldung per Passwort ist wieder aktiviert.",notificationPasswordLoginError:"Die Anmeldung per Passwort konnte nicht aktiviert werden.",enablePasswordLoginConfirm:"Die Anmeldung per Passwort f\xFCr diesen Kunden wieder aktivieren? Der Kunde wird per Mail dar\xFCber informiert."},dashboard:{group:"Passkeys",salesChannel:"Verkaufskanal",salesChannelHelp:"Nur Kunden dieses Verkaufskanals ber\xFCcksichtigen. Leer lassen f\xFCr alle Verkaufskan\xE4le.",loadError:"Die Daten konnten nicht geladen werden.",range:{"7Days":"Letzte 7 Tage","30Days":"Letzte 30 Tage","90Days":"Letzte 90 Tage","180Days":"Letzte 180 Tage","360Days":"Letzte 360 Tage"},summary:{label:"Passkey-Nutzung",description:"Kunden mit Passkeys, Anteil an allen Kunden und Anmeldung nur per Passkey",customersWithPasskey:"Kunden mit Passkey",adoptionRate:"{rate} von {customers} Kunden",passkeys:"Passkeys",activeCustomers:"In den letzten {days} Tagen verwendet",passwordLoginDisabled:"Passwort-Login deaktiviert"},added:{label:"Hinzugef\xFCgte Passkeys",description:"Anzahl der hinzugef\xFCgten Passkeys pro Tag",seriesName:"Hinzugef\xFCgte Passkeys"},recent:{label:"Zuletzt hinzugef\xFCgte Passkeys",description:"Die neuesten Passkeys mit ihren Kunden",limit:"Anzahl der Eintr\xE4ge",columnCustomer:"Kunde",empty:"Es wurden noch keine Passkeys hinzugef\xFCgt."}}}};var p={"tinect-passkeys":{customerCard:{title:"Passkeys",columnName:"Name",columnCreatedAt:"Created",columnLastUsedAt:"Last used",neverUsed:"Never",empty:"This customer has no passkeys.",actionDelete:"Remove",deleteConfirm:'Really remove passkey "{name}"? The customer can no longer log in with it.',notificationDeleteSuccess:"The passkey has been removed.",notificationDeleteError:"The passkey could not be removed.",passwordLoginDisabled:"The customer disabled the password login and only logs in with passkeys.",actionEnablePasswordLogin:"Enable password login again",notificationPasswordLoginEnabled:"The password login has been enabled again.",notificationPasswordLoginError:"The password login could not be enabled.",enablePasswordLoginConfirm:"Enable the password login for this customer again? The customer is informed by e-mail."},dashboard:{group:"Passkeys",salesChannel:"Sales channel",salesChannelHelp:"Only include customers of this sales channel. Leave empty for all sales channels.",loadError:"The data could not be loaded.",range:{"7Days":"Last 7 days","30Days":"Last 30 days","90Days":"Last 90 days","180Days":"Last 180 days","360Days":"Last 360 days"},summary:{label:"Passkey usage",description:"Customers with passkeys, share of all customers and passkey-only logins",customersWithPasskey:"Customers with passkey",adoptionRate:"{rate} of {customers} customers",passkeys:"Passkeys",activeCustomers:"Used in the last {days} days",passwordLoginDisabled:"Password login disabled"},added:{label:"Added passkeys",description:"Number of added passkeys per day",seriesName:"Added passkeys"},recent:{label:"Recently added passkeys",description:"The latest passkeys with their customers",limit:"Number of entries",columnCustomer:"Customer",empty:"No passkeys have been added yet."}}}};var{ApiService:n}=Shopware.Classes,o=class extends n{constructor(t,s,a="_action/tinect-passkeys"){super(t,s,a)}getCustomerPasskeys(t){return this.httpClient.get(`${this.getApiBasePath()}/customer/${t}`,{headers:this.getBasicHeaders()}).then(s=>n.handleResponse(s))}enableCustomerPasswordLogin(t){return this.httpClient.post(`${this.getApiBasePath()}/customer/${t}/enable-password-login`,{},{headers:this.getBasicHeaders()}).then(s=>n.handleResponse(s))}deleteCustomerPasskey(t,s){return this.httpClient.delete(`${this.getApiBasePath()}/customer/${t}/${s}`,{headers:this.getBasicHeaders()}).then(a=>n.handleResponse(a))}getDashboardSummary(t={}){return this.getDashboard("summary",t)}getDashboardRecent(t={}){return this.getDashboard("recent",t)}getDashboardAddedPerDay(t={}){return this.getDashboard("added-per-day",t)}getDashboard(t,s){return this.httpClient.get(`${this.getApiBasePath()}/dashboard/${t}`,{params:Object.fromEntries(Object.entries(s).filter(([,a])=>a!=null)),headers:this.getBasicHeaders()}).then(a=>n.handleResponse(a))}};var l="tinect-passkeys",c={name:"salesChannelId",type:"entity",entity:"sales_channel",label:"tinect-passkeys.dashboard.salesChannel",helpText:"tinect-passkeys.dashboard.salesChannelHelp",default:null},A=!1;function G(){let e=Shopware.FroshDashboard;A||!e||(A=!0,e.registerGroup({id:l,label:"tinect-passkeys.dashboard.group",position:40}),e.registerWidget({id:"tinect-passkeys-widget-summary",label:"tinect-passkeys.dashboard.summary.label",description:"tinect-passkeys.dashboard.summary.description",icon:"regular-key",component:"tinect-passkeys-widget-summary",defaultSize:"medium",supportedSizes:["small","medium","large","full"],group:l,acl:["customer.viewer"],settings:[c]}),e.registerWidget({id:"tinect-passkeys-widget-added",label:"tinect-passkeys.dashboard.added.label",description:"tinect-passkeys.dashboard.added.description",icon:"regular-chart-line",component:"tinect-passkeys-widget-added",defaultSize:"medium",supportedSizes:["medium","large","full"],group:l,acl:["customer.viewer"],settings:[c]}),e.registerWidget({id:"tinect-passkeys-widget-recent",label:"tinect-passkeys.dashboard.recent.label",description:"tinect-passkeys.dashboard.recent.description",icon:"regular-list",component:"tinect-passkeys-widget-recent",defaultSize:"large",supportedSizes:["medium","large","full"],group:l,acl:["customer.viewer"],settings:[c,{name:"limit",type:"select",label:"tinect-passkeys.dashboard.recent.limit",default:10,options:[{value:5,label:"5"},{value:10,label:"10"},{value:20,label:"20"},{value:50,label:"50"}]}]}))}Shopware.Component.register("tinect-passkeys-widget-summary",()=>Promise.resolve().then(()=>(k(),g)));Shopware.Component.register("tinect-passkeys-widget-added",()=>Promise.resolve().then(()=>(D(),v)));Shopware.Component.register("tinect-passkeys-widget-recent",()=>Promise.resolve().then(()=>(x(),_)));Shopware.Component.override("frosh-dashboard-grid",{beforeCreate(){G()}});Shopware.Application.addServiceProvider("tinectPasskeyApiService",e=>{let t=Shopware.Application.getContainer("init");return new o(t.httpClient,e.loginService)});Shopware.Component.register("tinect-passkey-customer-card",()=>Promise.resolve().then(()=>(N(),E)));Shopware.Component.override("sw-customer-detail-base",()=>Promise.resolve().then(()=>(R(),I)));Shopware.Locale.extend("de-DE",m);Shopware.Locale.extend("en-GB",p);})();
