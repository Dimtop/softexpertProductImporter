<?php

/**
 * Plugin Name: Softexpert Products Importer
 */

include_once WP_PLUGIN_DIR."/seProductImporter/Parser/CSVParser.php";
include_once  WP_PLUGIN_DIR."/seProductImporter/Parser/WoocommerceEngine.php";

function sepiUI() {
    // check user capabilities
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    include_once(WP_PLUGIN_DIR."/seProductImporter/Templates/adminPage.php");
}


function sepiMenuPage(){
    add_menu_page(
        'Softexpert Products Importer',
        'Softexpert Products Importer Options',
        'manage_options',
        'sepi',
        'sepiUI',
        '',
        20
    );
}


function sepiScripts(){
    wp_enqueue_script( 'uploadFile', site_url() ."/wp-content/plugins/seProductImporter/Scripts/uploadFile.js", array(), '1.0.0', true );
    wp_localize_script( 'uploadFile', 'ajaxData', array( 'url' => admin_url( 'admin-ajax.php' )));
}


//AJAX
function runImport(){
    $fileName="";
    $imagesFileName="";
    if($_FILES["sepiFile"]["name"]!=""){
        try{
            
            $pathInfo = pathinfo($_FILES["sepiFile"]["name"]);
            $fileName = __DIR__ ."/Data/" . time() . ".". $pathInfo["extension"];

            move_uploaded_file($_FILES['sepiFile']['tmp_name'], $fileName);
        }
        catch(Exception $e){
            echo json_encode($e->getMessage());
            die();
        }
    }
    if($_FILES["sepiImagesFile"]["name"]!=""){
        try{
            
            $pathInfo = pathinfo($_FILES["sepiImagesFile"]["name"]);
            $time =  time() ;
            $imagesFileName = __DIR__ ."/Images/" . $time . ".". $pathInfo["extension"];
            $imagesFolder = explode(".",$imagesFileName)[0];
            $imagesFolderName = $time;
            echo $imagesFileName;
            move_uploaded_file($_FILES['sepiImagesFile']['tmp_name'], $imagesFileName);
            mkdir($imagesFolder,0777,true);
            $zip = new ZipArchive;
            if($zip->open($imagesFileName)===TRUE){
                $zip->extractTo($imagesFolder);
                $zip->close();
            }
        }
        catch(Exception $e){
            echo json_encode($e->getMessage());
            die();
        }
    }

    $csvp = new CSVParser($fileName);
    $data = $csvp->getData();
    $data = array_slice($data,0,100);
    $we = new WoocommerceEngine($data,$_POST["mode"],$imagesFolder,$imagesFolderName);
    $we->createProducts();
    

    die();
}

function getLogs(){
    $data = file_get_contents(__DIR__."/Logs/mainLog.txt", true);
    echo json_encode($data);
    die();
}


function sepiInit(){

    add_action('admin_menu', 'sepiMenuPage');
    add_action("admin_enqueue_scripts","sepiScripts");
    add_action( 'wp_ajax_runImport', 'runImport' );
    add_action( 'wp_ajax_nopriv_runImport', 'runImport' );
    add_action( 'wp_ajax_getLogs', 'getLogs' );
    add_action( 'wp_ajax_nopriv_getLogs', 'getLogs' );
}


//Call Init
sepiInit();