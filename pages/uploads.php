<?php
include_once ("./config.php");
$db = new DB();


?>
<style>
    /* ===== Uploads page polish (scoped to .uploads-page) ===== */
    .uploads-page { animation: upIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) both; }
    @keyframes upIn { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }

    .uploads-page .up-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin: 0.5rem 0 1.6rem; }
    .uploads-page .up-title {
        font-family: var(--font-display, 'Bricolage Grotesque', sans-serif);
        font-weight: 800; font-size: 2rem; line-height: 1; letter-spacing: -0.02em; margin: 0;
        background: linear-gradient(100deg, #fff 35%, var(--light-orange, #ffb24d));
        -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
    }
    .uploads-page .up-sub { color: var(--text-muted, #9c9389); margin: 0.45rem 0 0; font-size: 0.95rem; }
    .uploads-page .up-actions { display: flex; gap: 0.6rem; flex-wrap: wrap; }
    .uploads-page .btn-custom {
        display: inline-flex; align-items: center; gap: 0.45rem; width: auto;
        font-weight: 600; font-size: 0.88rem; text-transform: none; letter-spacing: 0;
        padding: 0.55rem 1.05rem; border-radius: 11px; text-decoration: none;
        color: var(--light-orange, #ffb24d); background: rgba(240, 140, 0, 0.08); border: 1px solid var(--border-color, rgba(240,140,0,0.2)); transition: all 0.2s ease;
    }
    .uploads-page .btn-custom:hover { background: rgba(240, 140, 0, 0.16); border-color: var(--primary-orange, #f08c00); color: #fff; }

    .upload-zone {
        border: 2px dashed var(--border-color, rgba(240,140,0,0.25)); border-radius: 20px; padding: 3rem 2rem; text-align: center;
        background: linear-gradient(180deg, rgba(31, 26, 21, 0.5), rgba(20, 17, 13, 0.6)); transition: border-color 0.25s ease, background 0.25s ease;
    }
    .upload-zone:hover { border-color: var(--primary-orange, #f08c00); background: rgba(240, 140, 0, 0.05); }
    .upload-zone .uz-icon {
        width: 74px; height: 74px; border-radius: 20px; display: grid; place-items: center; margin: 0 auto 1.1rem; font-size: 2rem;
        background: rgba(240, 140, 0, 0.12); color: var(--primary-orange, #f08c00); border: 1px solid var(--border-color, rgba(240,140,0,0.2));
    }
    .upload-zone .uz-title { font-family: var(--font-display, 'Bricolage Grotesque', sans-serif); font-weight: 700; font-size: 1.3rem; color: #f4eee4; }
    .upload-zone .uz-sub { color: var(--text-muted, #9c9389); font-size: 0.9rem; margin: 0.45rem 0 1.4rem; }

    /* jquery.uploadfile generated controls */
    .uploads-page .ajax-upload-dragdrop { border: none !important; background: transparent !important; width: auto !important; display: inline-block; margin: 0 !important; }
    .uploads-page .ajax-file-upload {
        background: linear-gradient(135deg, var(--light-orange, #ffb24d), var(--dark-orange, #d97706)) !important; border: none !important; color: #241400 !important;
        border-radius: 12px !important; padding: 0.7rem 1.6rem !important; font-weight: 700 !important; font-family: var(--font-body, 'Hanken Grotesk', sans-serif) !important;
        box-shadow: 0 10px 24px -10px rgba(240, 140, 0, 0.6) !important; height: auto !important;
    }
    .uploads-page .ajax-file-upload:hover { filter: brightness(1.05); }
    .uploads-page .ajax-file-upload-statusbar { background: var(--bg-dark, #17130f) !important; border: 1px solid var(--border-color, rgba(240,140,0,0.2)) !important; border-radius: 12px !important; color: #e7e0d6 !important; margin: 1rem auto 0 !important; padding: 0.7rem !important; }
    .uploads-page .ajax-file-upload-filename { color: #d7cfc4 !important; }
    .uploads-page .ajax-file-upload-progress { background: rgba(255, 255, 255, 0.08) !important; border: none !important; border-radius: 999px !important; overflow: hidden; }
    .uploads-page .ajax-file-upload-bar { background: linear-gradient(90deg, var(--light-orange, #ffb24d), var(--dark-orange, #d97706)) !important; }
    .uploads-page .ajax-file-upload-red { background: rgba(255, 107, 93, 0.18) !important; border: 1px solid rgba(255,107,93,0.3) !important; color: #ff7a66 !important; border-radius: 8px !important; }
    .uploads-page .ajax-file-upload-green { color: #34d399 !important; }
</style>

<div class="container uploads-page">
    <div class="up-head">
        <div>
            <h1 class="up-title">Upload Documents</h1>
            <p class="up-sub">Add PDF documents to the archive — drag &amp; drop or browse.</p>
        </div>
        <div class="up-actions">
            <a href="./?p=settings" class="btn btn-custom"><i class="fa fa-arrow-left"></i> Back</a>
            <a href="./?p=settings_uploads" class="btn btn-custom"><i class="fa fa-cog"></i> Uploads Setting</a>
        </div>
    </div>

    <div class="search-result" id="search-result">
        <div class="upload-zone">
            <div class="uz-icon"><i class="fas fa-cloud-upload-alt"></i></div>
            <div class="uz-title">Drag &amp; drop your PDFs here</div>
            <div class="uz-sub">or use the button below &middot; PDF only &middot; up to 50 files</div>
            <div id="multiple_file_uploader">Upload</div>
        </div>
    </div>
    <br>
</div>



<script>
    $(document).ready(function()
    {
        $("#multiple_file_uploader").uploadFile({
            fileName : "myfile",
            url : "pages/upload.php",
            multiple : true,
            maxFileCount : 50,
            allowedTypes : "pdf",
            showProgress : true
        });
    });

    function showFile(path) {
        var file_path = "<?php echo BASE_URL; ?>serve_file.php?file="+encodeURIComponent(path);
        PDFObject.embed(file_path, "#search-result");
    }

    $(document).ready(function(){
        $("#input-description").on('keyup', function() {
            var text = $(this).val();
            if(text.length > 3) {
                search();
            }
        });
        $("#input-number").on('keyup', function() {
            var text = $(this).val();
            if(text.length > 2 || text == '') {
                search();
            }
        });
    });

    function search() {
        var document_type = $("#input-document_type").val();
        var year = $("#input-year").val();
        var description = $("#input-description").val();
        var number = $("#input-number").val();
        $("#search-result").html("<div class='loader-icon'><i class='fa fa-spinner fa-spin fa-3x'></i></div>")
        if(year == '' || (description == '' && number == '' && document_type == '')) {
            return;
        }

        $.ajax({
            type: "POST",
            url: "./ajax.php?fx=search_file",
            data: "document_type=" + document_type + "&year=" + year + "&description=" + description + "&number="+ number + "",
            cache: false,
            success: function (data) {
                if(data && (data != '')) {
                    json_data = JSON.parse(data);
                    if(typeof(json_data) === 'object') {
                        console.log(json_data);
                        if(json_data.id !== undefined) {
                            // $("#search-result").html(data);

                            showFile(data.path);
                        } else {
                            if(json_data.length > 0) {
                                $("#search-result").html('');
                                var html = "<ul class='list-group'>";
                                for(var i = 0; i < json_data.length; i++) {
                                    var obj = json_data[i];
                                    html += "<li class='list-group-item' onclick='showFile(\"" + obj['path'] + "\")'>PF/ARCH/" + obj['id'] + " [" + obj['name'] + "] " + trimText(obj['description']) + "</li>";

                                }
                                html += "</ul>";
                                $("#search-result").append(html);
                            } else {
                                $("#search-result").html("<div class='loader-icon text-center'>No result!</div>");
                            }
                        }
                    } else {
                        $("#search-result").html("Invalid result!");
                    }
                } else {
                    Swal.fire('Search Failed');
                }
            }
        });

        function trimText(text) {
            return text.substring(0,60) + "...";
        }


    }
</script>
