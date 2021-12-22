jQuery("#sepiUpload").click(()=>{

    var fd = new FormData();
    var file = jQuery("#sepiFile").prop("files")[0];
    if(!file){
        alert("Please select a file");
        return;
    }
    fd.append("sepiFile",file);
    fd.append("action","runImport");

    jQuery.ajax({
        type:"post",
        processData: false,
        contentType: false,
        url:ajaxData.url,
        data:fd,
        success:(res)=>{
            res=JSON.parse(res);
            console.log(res)
            var logFilePath = res;

        }
    })
    var logInterval = setInterval(()=>{
        var fd1 = new FormData();
        fd1.append("action","getLogs");
        jQuery.ajax({
            type: "post",
            processData: false,
            contentType: false,
            url: ajaxData.url,
            data: fd1,
            success:(res)=>{
                res=JSON.parse(res)
                console.log(res)
                jQuery("#sepiLogs").val( res)
                if(res.indexOf("Done")>=0){
                    clearInterval(logInterval);
                }

            }
        })
    },3000)
});

jQuery(document).ready(()=>{

})