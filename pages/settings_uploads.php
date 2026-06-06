<?php
include_once ("./config.php");
$db = new DB();
?>

<br />
<div class="container">
    <div class="row p-3">
        <div class="col-12">
            <div class="btn-group float-right" role="group" aria-label="Basic example">
                <a href="./?p=settings" class="btn btn-custom "><i class="fa fa-arrow-left">&nbsp;</i> Back</a>
                <a href="./?p=settings_uploads" class="btn btn-custom "><i class="fa fa-folder">&nbsp;</i> Uploads Setting</a>
            </div>
        </div>
    </div>
    <div class="search-result" id="search-result" style="min-height:400px; ">
        <!--        <div class="text-center">Still Under Construction</div>-->
        <div class="row">
<div class="col-md-12">
    <div id="multiple_file_uploader">Upload</div>
</div>


        </div>
    </div>
    <br>
</div>



<script>
    $(document).ready(function()
    {
        $("#multiple_file_uploader").uploadFile({
            fileName : "myfile",
            url : "./pages/upload.php",
            multiple : true,
            maxFileCount : 5,
            allowedTypes : "jpg,png,gif,pdf",
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