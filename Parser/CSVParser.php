<?php


class CSVParser{

    private  $filePath;
    private $data;

    function __construct($filePathToSet){
        $this->filePath = $filePathToSet;
        $this->data = $this->parseCSV();

    }



    private function parseCSV(){
        $fileResource = fopen($this->filePath,"r");
        $data = [];
        if(!$fileResource){
            throw new Error("There is a problem with the requested file.");
            return;
        }
        while (($currData =  fgetcsv($fileResource,5000000,";",'"',"\\")) !== FALSE) {
            $data[] = $currData;
        }

        fclose($fileResource);

        return $data;
    }

    public function getData(){
        return $this->data;
    }
}