<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);



class WoocommerceEngine{

    private $csvData;
    private $logFile;
    private $logFileName;
    private $logFilePath;
    private $mode;
    private $includedProducts;

    public function __construct($csvDataToSet,$modeToSet)
    {
        $this->csvData = $csvDataToSet;
        $this->createLogFile();

        $this->mode = $modeToSet;
        $this->includedProducts = [];
    }

    private function createLogFile(){
        //$this->logFileName = time() . ".txt";
       // $this->logFilePath = dirname(dirname(__FILE__))."/Logs/".  $this->logFileName;
        $this->logFileName = "mainLog.txt";
        $this->logFilePath = dirname(dirname(__FILE__))."/Logs/".  $this->logFileName;
        $this->logFile = fopen(      $this->logFilePath ,"a+") or die("Unable to open file!");
        file_put_contents($this->logFilePath, "");
        fwrite($this->logFile , "\n#####################");
        fwrite($this->logFile , "\nInitialized import");
        fwrite($this->logFile , "\n#####################");
    }
    private function closeLogFile(){
       // file_put_contents($this->logFilePath, "");
        fclose($this->logFile);
    }


    public function getLogFilePath(){
        return $this->logFilePath;
    }
    public function getLogFileName(){
        return $this->logFileName;
    }
    private function createVariationFromDataRow($row){
        return array(
            "sku"=>$row[8],
            "size"=>$row[2],
            "color"=>$row[5],
            "parent"=>$row[6],
            "regular_price"=>$row[13]/100,
            "category"=>$row[14],
            "subcategory"=>$row[15],
            "name"=>$row[6]
        );
    }

    private function manageParentAttributes($productData){
        global $wpdb;

        $productData["colors"] = $this->normalizeAttributesArray($productData["colors"]);

        if(!$wpdb->get_results("SELECT * FROM " . $wpdb->prefix ."woocommerce_attribute_taxonomies WHERE attribute_name='color'")){
            fwrite($this->logFile , "\nThe color attribute was not found.");
            return array(
                "error"=>"The color attribute was not found."
            );
        }
        if(!$wpdb->get_results("SELECT * FROM " . $wpdb->prefix ."woocommerce_attribute_taxonomies WHERE attribute_name='size'")){
            fwrite($this->logFile , "\nThe size attribute was not found.");
            return array(
                "error"=>"The size attribute was not found."
            );
        }
        $data = [];
        foreach ($productData["colors"] as $key=>$val){
            $this->createTerm($val,"pa_color",NULL);
        }
        foreach ($productData["sizes"] as $key=>$val){
            $this->createTerm($val,"pa_size",NULL);
        }

        return "asd";
    }

    private function manageParentCategories($productData){

        $parentData = $this->createTerm($productData["category"],"product_cat",NULL);
        if($parentData){
            $childData = $this->createTerm($productData["subcategory"],"product_cat",$parentData["termID"]);

            $hierarchy = get_option("product_cat_children",false);

            if(array_key_exists($parentData["termID"],$hierarchy)){
                $hierarchy[$parentData["termID"]][] = $childData["termID"];
            }else{
                $hierarchy[$parentData["termID"]] = [$childData["termID"]];
            }
            update_option("product_cat_children",serialize($hierarchy),true);
            fwrite($this->logFile , "\n" . "Updated categories hierarchy. Moving on.");
            return $parentData;


        }else{
            return NULL;
        }


    }

    private function createTerm($termName,$taxonomy,$parent){
        global $wpdb;
        if(!$this->termExists($termName,$taxonomy,$parent)){
            fwrite($this->logFile , "\n" . "Term ". $termName ." doesn't exist. Creating the term.");
            $wpdb->insert($wpdb->prefix."terms",array(
                "term_id"=>NULL,
                "name"=>$termName,
                "slug"=>$parent?$this->slugify($termName."-".$parent):$this->slugify($termName),
                "term_group"=>0
            ));
            $termID = $wpdb->insert_id;
            $wpdb->insert($wpdb->prefix."term_taxonomy",array(
                "term_taxonomy_id"=>$termID,
                "term_id"=>$termID,
                "taxonomy"=>$taxonomy,
                "parent"=>$parent?:0,
                "count"=>0
            ));
            $taxonomyID= $wpdb->insert_id;
            return array(
                "termID"=>$termID,
                "taxonomyID"=>$taxonomyID
            );
        }else{
            fwrite($this->logFile , "\n" . "Term ". $termName ." already exists. Doing what's necessary.");
            if($parent){
                $existingTerm  = get_term_by("slug",$this->slugify(trim($termName."-".$parent)),"product_cat");

            }else{
                $existingTerm  = get_term_by("slug",$this->slugify(trim($termName)),"product_cat");

            }

            if(!$existingTerm){
                return NULL;
            }else{
                $existingTaxonomy = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix ."term_taxonomy WHERE term_id='". $existingTerm->term_id ."'");
                if(!$existingTaxonomy || count($existingTaxonomy)==0){
                    return NULL;
                }

                return array(
                    "termID"=>$existingTerm->term_id,
                    "taxonomyID"=>$existingTaxonomy[0]->term_taxonomy_id
                );
            }
        }
        return NULL;
    }



    private function termExists($termName,$taxonomyName,$parentID){
        if($parentID){
            $existingTerm = get_term_by("slug",$this->slugify(trim($termName."-".$parentID)),$taxonomyName);
        }else{
            $existingTerm = get_term_by("slug",$this->slugify(trim($termName)),$taxonomyName);
        }



        if($existingTerm){

            if($existingTerm->parent == $parentID){
                return  true;
            }

        }

        return false;
       /*
        *
                global $wpdb;
         if($parentID){
            $data = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix ."terms AS t INNER JOIN " . $wpdb->prefix ."term_taxonomy AS tt ON t.term_id=tt.term_id 
        WHERE t.name='" . $termName . "' AND tt.taxonomy='". $taxonomyName ."' AND tt.parent!='".$parentID."';");
            echo print_r($data);
        }else{
            $data = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix ."terms AS t INNER JOIN " . $wpdb->prefix ."term_taxonomy AS tt ON t.term_id=tt.term_id 
        WHERE t.name='" . $termName . "' AND tt.taxonomy='". $taxonomyName ."';");
        }


        if(!$data || count($data)==0){
            return false;
        }
        return true;*/
    }

    private function constructProductAttributeMeta($productData){
        return array(
            "pa_color"=>array(
                'name' => 'pa_color',
                'value' => '',
                'position' => 0,
                'is_visible' => 0,
                'is_variation' => 1,
                'is_taxonomy' => 1,
            ),
            "pa_size"=>array(
                'name' => 'pa_size',
                'value' => '',
                'position' => 0,
                'is_visible' => 0,
                'is_variation' => 1,
                'is_taxonomy' => 1,
            ),
        );
    }

    private function writeParentProductAttributes($productData,$parentProductID){
        foreach ($productData["colors"] as $key=>$value){
            wp_set_post_terms( $parentProductID, $value, "pa_color", true );
        }
        foreach ($productData["sizes"] as $key=>$value){
            wp_set_post_terms( $parentProductID, $value, "pa_size", true );
        }
    }

    private function writeParentProductCategories($productData,$parentProductID){
        $category= get_term_by("slug",$this->slugify($productData["category"]),"product_cat");
        $subcategory = get_term_by("slug",$this->slugify($productData["subcategory"]."-".$category->term_id),"product_cat");

        if($category){
            wp_set_post_terms( $parentProductID, $category->term_id, "product_cat", true );
        }
        if($subcategory){
            wp_set_post_terms( $parentProductID, $subcategory->term_id, "product_cat", true );
        }



    }

    private function writeVariationAttributes($productData,$variationID){
        update_post_meta( $variationID, 'attribute_pa_color', $this->slugify(mb_strtoupper(trim($productData["color"]))) );
        update_post_meta( $variationID, 'attribute_pa_size', $this->slugify(mb_strtoupper(trim($productData["size"]))) );
    }

    private function manageExistingParentProduct($productData){
        $existingProductID=  wc_get_product_id_by_sku( $productData["sku"] );
        if(!$existingProductID){
            fwrite($this->logFile , "\n" . "Product " . $productData["sku"] . " doesn't exist. We will create it.");
            return;
        }
        $existingProduct = wc_get_product($existingProductID);
        if($existingProduct){
            fwrite($this->logFile , "\n" . "Product " . $productData["sku"] . " exists. We will update it.");
            if ($existingProduct->is_type('variable') && get_post_status($existingProductID)=="publish")
            {
                /*foreach ($existingProduct->get_children() as $child_id)
                {
                    $child = wc_get_product($child_id);
                    $child->delete(true);
                }*/
            }
            //$existingProduct->delete(true);
            fwrite($this->logFile , "\n" . "The product ID that is going to be used is " . $existingProductID);
            return $existingProductID;
        }
        return false;
    }

    private function createParentProduct($productData){

        //Preparation
        fwrite($this->logFile , "\n" . "Begenning the creation of product " .   $productData["sku"]);
       $this->manageParentAttributes($productData);
       $this->manageParentCategories($productData);
       $existingProductID = $this->manageExistingParentProduct($productData);
        $parentProductData= array(
            'post_author'   => 1,
            'post_name'     => $productData["name"],
            'post_title'    => $productData["name"],
            'post_content'  => $productData["name"],
            'post_excerpt'  => $productData["name"],
            'post_status'   => 'publish',
            'ping_status'   => 'closed',
            'post_type'     => 'product'
        );


        //Parent product creation
        if($existingProductID){
            $parentProductData["ID"] = $existingProductID;
            wp_insert_post( $parentProductData );
            $parentProduct = new WC_Product_Variable( $existingProductID );

            //Attributes
            update_post_meta($existingProductID,"_product_attributes",$this->constructProductAttributeMeta($productData));
            $this->writeParentProductAttributes($productData,$existingProductID);

            //Categories
            $this->writeParentProductCategories($productData,$existingProductID);

            //Data
            $parentProduct->set_sku( $productData["sku"] );
            update_post_meta($existingProductID,"_stock_status","instock");
            $parentProduct->save();

            //Include product
            $this->includedProducts[] = $existingProductID;

            return $existingProductID;
        }else{
            $parentProductID = wp_insert_post( $parentProductData );
            $parentProduct = new WC_Product_Variable( $parentProductID );
            //$parentProduct->save();
            fwrite($this->logFile , "\n" . "Product " .   $parentProductID  . " has been created. We will now set the attributes, categories and some product data.");

            //Attributes
            update_post_meta($parentProductID,"_product_attributes",$this->constructProductAttributeMeta($productData));
            $this->writeParentProductAttributes($productData,$parentProductID);

            //Categories
            $this->writeParentProductCategories($productData,$parentProductID);

            //Data
            $parentProduct->set_sku( $productData["sku"] );
            update_post_meta($parentProductID,"_stock_status","instock");
            $parentProduct->save();
            fwrite($this->logFile , "\n" . "Product " .   $parentProductID  . " has been finished.");


            //Include product
            $this->includedProducts[] = $parentProductID;

            return $parentProductID;
        }




    }

    private function createVariations($parentProductID,$productData){
        $parentProduct = wc_get_product($parentProductID);
        fwrite($this->logFile , "\n" . "Beginning the creation of variation " .   $productData["sku"]);
        foreach ($productData["variations"] as $key=>$val){
            $currVariationData = array(
                'post_title'  => $val["name"],
                'post_name'   => $val["name"],
                'post_status' => 'publish',
                'post_parent' => $parentProductID,
                'post_type'   => 'product_variation'
            );
            $existingVariationID = wc_get_product_id_by_sku( $val["sku"] );
            $existingVariation = wc_get_product($existingVariationID);
            if($existingVariation){
                fwrite($this->logFile , "\n" . "The variation " . $val["sku"] . " already exists. We will update it." );
                $currVariationData["ID"] = $existingVariationID;
                wp_update_post( $currVariationData );
                $currVariation = new WC_Product_Variation( $existingVariationID );
                $this->writeVariationAttributes($val,$existingVariationID);
                $currVariation->set_sku( $val['sku'] );
                $currVariation->set_price( $val['regular_price'] );
                $currVariation->set_regular_price( $val['regular_price'] );
                $currVariation->set_manage_stock(false);
                update_post_meta($existingVariationID,"_stock_status","instock");
                $currVariation->save();
                //Include product
                $this->includedProducts[] = $existingVariationID;
            }else{
                $currVariationID = wp_insert_post( $currVariationData );
                $currVariation = new WC_Product_Variation( $currVariationID );

                fwrite($this->logFile , "\n" . "Creating the variation's attributes and adding some data." );
                //Attributes
                $this->writeVariationAttributes($val,$currVariationID);

                //Data
                $currVariation->set_sku( $val['sku'] );
                $currVariation->set_price( $val['regular_price'] );
                $currVariation->set_regular_price( $val['regular_price'] );
                $currVariation->set_manage_stock(false);
                update_post_meta($currVariationID,"_stock_status","instock");
                $currVariation->save();
                fwrite($this->logFile , "\n" . "The variation ". $currVariationID . " has been finished." );

                //Include product
                $this->includedProducts[] = $currVariationID;
            }


        }
    }

    public function createProducts(){
        $parsedProducts = $this->getParentProducts();

        foreach ($parsedProducts as $key=>$val){

            fwrite($this->logFile , "\n\n" );
            $parentProductID = $this->createParentProduct($val);
            $this->createVariations($parentProductID,$val);

        }
        $this->draftNotExistingProducts();
        fwrite($this->logFile , "\n\n" );
        fwrite($this->logFile , "Done!!!!" );
        $this->closeLogFile();
        return;
    }


    private function draftNotExistingProducts(){
        global $wpdb;
        $allProducts = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix ."posts WHERE post_type='product'");
        foreach ($allProducts as $key=>$val){
            if(!in_array($val->ID,$this->includedProducts)&&get_post_status($val->ID)!="trash"){
                wp_update_post(array(
                    'ID'    =>  $val->ID,
                    'post_status'   =>  'draft'
                ));
                fwrite($this->logFile , "\n" . "Drafted". $val->ID );
            }
        }
    }

    private function normalizeAttributesArray($attrArray){
        $attrArrayNormalized = [];
        foreach ($attrArray as $key=>$val){
            $attrArrayNormalized[] = mb_strtoupper(trim($val));
        }
        return $attrArrayNormalized;
    }


    private function getParentProducts(){
        $parentProducts = [];

        foreach ($this->csvData as $key=>$val){
            if(!array_key_exists($val[6],$parentProducts)){
                $parentProducts[$val[6]] = array(
                    "name"=>$val[6],
                    "sku"=>$val[0],
                    "sizes"=>[$val[2]],
                    "colors" => [$val[5]],
                    "variations"=>[$this->createVariationFromDataRow($val)],
                    "category"=>$val[14],
                    "subcategory"=>$val[15]
                );
            }else{
                if(!in_array($val[2],$parentProducts[$val[6]]["sizes"])){
                    $parentProducts[$val[6]]["sizes"][] = $val[2];
                }
                if(!in_array($val[5],$parentProducts[$val[6]]["colors"])){
                    $parentProducts[$val[6]]["colors"][] = $val[5];
                }
                $parentProducts[$val[6]]["variations"][] =$this->createVariationFromDataRow($val);
            }
        }

        return $parentProducts;
    }
    private function slugify($str, $options = array()){
        // Make sure string is in UTF-8 and strip invalid UTF-8 characters
        $str = mb_convert_encoding((string)$str, 'UTF-8', mb_list_encodings());

        $defaults = array(
            'delimiter' => '-',
            'limit' => null,
            'lowercase' => true,
            'replacements' => array(),
            'transliterate' => true,
        );

        // Merge options
        $options = array_merge($defaults, $options);

        $char_map = array(
            // Latin
            '??' => 'A', '??' => 'A', '??' => 'A', '??' => 'A', '??' => 'A', '??' => 'A', '??' => 'AE', '??' => 'C',
            '??' => 'E', '??' => 'E', '??' => 'E', '??' => 'E', '??' => 'I', '??' => 'I', '??' => 'I', '??' => 'I',
            '??' => 'D', '??' => 'N', '??' => 'O', '??' => 'O', '??' => 'O', '??' => 'O', '??' => 'O', '??' => 'O',
            '??' => 'O', '??' => 'U', '??' => 'U', '??' => 'U', '??' => 'U', '??' => 'U', '??' => 'Y', '??' => 'TH',
            '??' => 'ss',
            '??' => 'a', '??' => 'a', '??' => 'a', '??' => 'a', '??' => 'a', '??' => 'a', '??' => 'ae', '??' => 'c',
            '??' => 'e', '??' => 'e', '??' => 'e', '??' => 'e', '??' => 'i', '??' => 'i', '??' => 'i', '??' => 'i',
            '??' => 'd', '??' => 'n', '??' => 'o', '??' => 'o', '??' => 'o', '??' => 'o', '??' => 'o', '??' => 'o',
            '??' => 'o', '??' => 'u', '??' => 'u', '??' => 'u', '??' => 'u', '??' => 'u', '??' => 'y', '??' => 'th',
            '??' => 'y',

            // Latin symbols
            '??' => '(c)',

            // Greek
            '??' => 'A', '??' => 'B', '??' => 'G', '??' => 'D', '??' => 'E', '??' => 'Z', '??' => 'H', '??' => '8',
            '??' => 'I', '??' => 'K', '??' => 'L', '??' => 'M', '??' => 'N', '??' => '3', '??' => 'O', '??' => 'P',
            '??' => 'R', '??' => 'S', '??' => 'T', '??' => 'Y', '??' => 'F', '??' => 'X', '??' => 'PS', '??' => 'W',
            '??' => 'A', '??' => 'E', '??' => 'I', '??' => 'O', '??' => 'Y', '??' => 'H', '??' => 'W', '??' => 'I',
            '??' => 'Y',
            '??' => 'a', '??' => 'b', '??' => 'g', '??' => 'd', '??' => 'e', '??' => 'z', '??' => 'h', '??' => '8',
            '??' => 'i', '??' => 'k', '??' => 'l', '??' => 'm', '??' => 'n', '??' => '3', '??' => 'o', '??' => 'p',
            '??' => 'r', '??' => 's', '??' => 't', '??' => 'y', '??' => 'f', '??' => 'x', '??' => 'ps', '??' => 'w',
            '??' => 'a', '??' => 'e', '??' => 'i', '??' => 'o', '??' => 'y', '??' => 'h', '??' => 'w', '??' => 's',
            '??' => 'i', '??' => 'y', '??' => 'y', '??' => 'i',

            // Turkish
            '??' => 'S', '??' => 'I', '??' => 'C', '??' => 'U', '??' => 'O', '??' => 'G',
            '??' => 's', '??' => 'i', '??' => 'c', '??' => 'u', '??' => 'o', '??' => 'g',

            // Russian
            '??' => 'A', '??' => 'B', '??' => 'V', '??' => 'G', '??' => 'D', '??' => 'E', '??' => 'Yo', '??' => 'Zh',
            '??' => 'Z', '??' => 'I', '??' => 'J', '??' => 'K', '??' => 'L', '??' => 'M', '??' => 'N', '??' => 'O',
            '??' => 'P', '??' => 'R', '??' => 'S', '??' => 'T', '??' => 'U', '??' => 'F', '??' => 'H', '??' => 'C',
            '??' => 'Ch', '??' => 'Sh', '??' => 'Sh', '??' => '', '??' => 'Y', '??' => '', '??' => 'E', '??' => 'Yu',
            '??' => 'Ya',
            '??' => 'a', '??' => 'b', '??' => 'v', '??' => 'g', '??' => 'd', '??' => 'e', '??' => 'yo', '??' => 'zh',
            '??' => 'z', '??' => 'i', '??' => 'j', '??' => 'k', '??' => 'l', '??' => 'm', '??' => 'n', '??' => 'o',
            '??' => 'p', '??' => 'r', '??' => 's', '??' => 't', '??' => 'u', '??' => 'f', '??' => 'h', '??' => 'c',
            '??' => 'ch', '??' => 'sh', '??' => 'sh', '??' => '', '??' => 'y', '??' => '', '??' => 'e', '??' => 'yu',
            '??' => 'ya',

            // Ukrainian
            '??' => 'Ye', '??' => 'I', '??' => 'Yi', '??' => 'G',
            '??' => 'ye', '??' => 'i', '??' => 'yi', '??' => 'g',

            // Czech
            '??' => 'C', '??' => 'D', '??' => 'E', '??' => 'N', '??' => 'R', '??' => 'S', '??' => 'T', '??' => 'U',
            '??' => 'Z',
            '??' => 'c', '??' => 'd', '??' => 'e', '??' => 'n', '??' => 'r', '??' => 's', '??' => 't', '??' => 'u',
            '??' => 'z',

            // Polish
            '??' => 'A', '??' => 'C', '??' => 'e', '??' => 'L', '??' => 'N', '??' => 'o', '??' => 'S', '??' => 'Z',
            '??' => 'Z',
            '??' => 'a', '??' => 'c', '??' => 'e', '??' => 'l', '??' => 'n', '??' => 'o', '??' => 's', '??' => 'z',
            '??' => 'z',

            // Latvian
            '??' => 'A', '??' => 'C', '??' => 'E', '??' => 'G', '??' => 'i', '??' => 'k', '??' => 'L', '??' => 'N',
            '??' => 'S', '??' => 'u', '??' => 'Z',
            '??' => 'a', '??' => 'c', '??' => 'e', '??' => 'g', '??' => 'i', '??' => 'k', '??' => 'l', '??' => 'n',
            '??' => 's', '??' => 'u', '??' => 'z'
        );

        // Make custom replacements
        $str = preg_replace(array_keys($options['replacements']), $options['replacements'], $str);

        // Transliterate characters to ASCII
        if ($options['transliterate']) {
            $str = str_replace(array_keys($char_map), $char_map, $str);
        }

        // Replace non-alphanumeric characters with our delimiter
        $str = preg_replace('/[^\p{L}\p{Nd}]+/u', $options['delimiter'], $str);

        // Remove duplicate delimiters
        $str = preg_replace('/(' . preg_quote($options['delimiter'], '/') . '){2,}/', '$1', $str);

        // Truncate slug to max. characters
        $str = mb_substr($str, 0, ($options['limit'] ? $options['limit'] : mb_strlen($str, 'UTF-8')), 'UTF-8');

        // Remove delimiter from ends
        $str = trim($str, $options['delimiter']);

        return $options['lowercase'] ? mb_strtolower($str, 'UTF-8') : $str;
    }

}

