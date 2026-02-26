<link rel="stylesheet" href="modules/addons/clientverify/assets/css/module.css?v={$version}">

{* ─── Verification Status Banner ─────────────────── *}
<div id="cv-status-banner" class="cv-status-loading">
    <i class="fas fa-spinner fa-spin"></i> Checking verification status...
</div>

{* ─── Upload Section ─────────────────────────────── *}
<div class="panel panel-default card cv-panel" id="cv-upload-panel">
    <div class="panel-heading card-header cv-panel-header">
        <h3 class="panel-title card-title m-0">
            <i class="fas fa-cloud-upload-alt"></i> Upload Documents
        </h3>
    </div>
    <div class="panel-body card-body">
        <p class="cv-upload-desc">
            Select a document type and upload your identification file.
            Accepted formats: <strong>{$allowedExtensions}</strong> — Max size: <strong>{$maxFileSize}MB</strong>
        </p>

        <div class="cv-upload-form">
            <div class="cv-doc-type-selector">
                <label><i class="fas fa-tag"></i> Document Type:</label>
                <div class="cv-chips" id="cv-doc-chips">
                    {* Chips will be populated by JS from docTypes *}
                </div>
            </div>

            <div class="cv-dropzone" id="cv-dropzone">
                <div class="cv-dropzone-inner">
                    <i class="fas fa-cloud-upload-alt cv-dropzone-icon"></i>
                    <p>Drag & drop your file here</p>
                    <span>or</span>
                    <label for="cv-file-input" class="btn btn-primary cv-browse-btn">
                        <i class="fas fa-folder-open"></i> Browse Files
                    </label>
                    <input type="file" id="cv-file-input" accept=".{$allowedExtensions|replace:',':',.'}" style="display:none;">
                </div>
                <div class="cv-dropzone-progress" id="cv-upload-progress" style="display:none;">
                    <div class="cv-progress-bar">
                        <div class="cv-progress-fill" id="cv-progress-fill"></div>
                    </div>
                    <span id="cv-progress-text">Uploading...</span>
                </div>
            </div>
        </div>
    </div>
</div>

{* ─── Documents Grid ─────────────────────────────── *}
<div class="panel panel-default card cv-panel">
    <div class="panel-heading card-header cv-panel-header">
        <h3 class="panel-title card-title m-0">
            <i class="fas fa-file-alt"></i> Uploaded Documents
        </h3>
    </div>
    <div class="panel-body card-body p-0">
        <div id="cv-client-docs-loading" class="cv-loading">
            <i class="fas fa-spinner fa-spin"></i> Loading documents...
        </div>

        <table class="table table-hover cv-table" id="cv-client-docs-table" style="display:none;">
            <thead>
                <tr>
                    <th>Document</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="cv-client-docs-body">
            </tbody>
        </table>

        <div id="cv-client-docs-empty" class="cv-empty-state" style="display:none;">
            <i class="fas fa-folder-open"></i>
            <p>You haven't uploaded any documents yet.</p>
        </div>
    </div>
</div>
