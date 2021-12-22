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
    if($_FILES["sepiFile"]["name"]!=""){
        try{

            $pathInfo = pathinfo($_FILES["sepiFile"]["name"]);
            $fileName = __DIR__ ."/Files/" . time() . ".". $pathInfo["extension"];
            move_uploaded_file($_FILES['sepiFile']['tmp_name'], $fileName);
            $csvp = new CSVParser($fileName);
            $data = $csvp->getData();
            $we = new WoocommerceEngine($data);
            $we->createProducts();
            //echo json_encode($we->getLogFilePath());

            die();


        }
        catch(Exception $e){
            echo json_encode($e->getMessage());
            die();
        }


    }
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