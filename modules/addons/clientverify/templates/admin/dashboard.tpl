{* Admin Dashboard Template — Client Verification Module *}

<div class="cv-admin-wrap">

    {* Module Header *}
    <div class="cv-header">
        <div class="cv-header-left">
            <h1><i class="fas fa-user-shield"></i> Client Verification</h1>
            <span class="cv-version">v{$version}</span>
        </div>
    </div>

    {* Bootstrap Tabs *}
    <ul class="nav nav-tabs cv-tabs" role="tablist">
        <li role="presentation" class="active">
            <a href="#cv-tab-users" aria-controls="cv-tab-users" role="tab" data-toggle="tab">
                <i class="fas fa-users"></i> Users
            </a>
        </li>
        <li role="presentation">
            <a href="#cv-tab-gateways" aria-controls="cv-tab-gateways" role="tab" data-toggle="tab">
                <i class="fas fa-credit-card"></i> Payment Gateways
            </a>
        </li>
    </ul>

    <div class="tab-content cv-tab-content">

        {* ─── Users Tab ─────────────────────────────────────── *}
        <div role="tabpanel" class="tab-pane active" id="cv-tab-users">
            <div class="cv-toolbar">
                <div class="cv-search-wrap">
                    <i class="fas fa-search"></i>
                    <input type="text" id="cv-user-search" class="form-control" placeholder="Search clients...">
                </div>
                <div class="cv-pagination-info">
                    <span id="cv-users-info"></span>
                </div>
            </div>

            <div id="cv-users-loading" class="cv-loading">
                <i class="fas fa-spinner fa-spin"></i> Loading clients...
            </div>

            <table class="table table-hover cv-table" id="cv-users-table" style="display:none;">
                <thead>
                    <tr>
                        <th class="cv-col-id" data-sort="id">ID <i class="fas fa-sort"></i></th>
                        <th class="cv-col-client" data-sort="firstname">Client <i class="fas fa-sort"></i></th>
                        <th class="cv-col-email" data-sort="email">Email <i class="fas fa-sort"></i></th>
                        <th class="cv-col-verified" data-sort="is_verified">Verified <i class="fas fa-sort"></i></th>
                        <th class="cv-col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="cv-users-body">
                </tbody>
            </table>

            <div class="cv-pagination" id="cv-users-pagination"></div>
        </div>

        {* ─── Gateways Tab ──────────────────────────────────── *}
        <div role="tabpanel" class="tab-pane" id="cv-tab-gateways">
            <div class="cv-gateway-intro">
                <p><i class="fas fa-info-circle"></i> Toggle enforcement to require client verification before checkout with specific payment gateways.</p>
            </div>

            <div id="cv-gw-loading" class="cv-loading">
                <i class="fas fa-spinner fa-spin"></i> Loading gateways...
            </div>

            <table class="table table-hover cv-table" id="cv-gw-table" style="display:none;">
                <thead>
                    <tr>
                        <th class="cv-col-gateway">Gateway</th>
                        <th class="cv-col-enforce">Require Verification</th>
                    </tr>
                </thead>
                <tbody id="cv-gw-body">
                </tbody>
            </table>
        </div>

    </div>

    {* ─── Document Review Modal ─────────────────────────────── *}
    <div class="modal fade" id="cv-docs-modal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title" id="cv-docs-modal-title">
                        <i class="fas fa-file-alt"></i> Documents — <span id="cv-docs-client-name"></span>
                    </h4>
                </div>
                <div class="modal-body" id="cv-docs-modal-body">
                    <div id="cv-docs-loading" class="cv-loading">
                        <i class="fas fa-spinner fa-spin"></i> Loading documents...
                    </div>
                    <div id="cv-docs-list"></div>
                    <div id="cv-docs-empty" class="cv-empty-state" style="display:none;">
                        <i class="fas fa-folder-open"></i>
                        <p>No documents uploaded by this client.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
    var cvModuleLink = '{$moduleLink}';
</script>
