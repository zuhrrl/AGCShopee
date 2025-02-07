<?php
// required for database
header('Content-Type: application/json');
session_start();
include "../config/config.php";
include "../lib/imagewebp.php";




$api_url = "https://api.kaosqu.com/agcshopee/";
$product_url = "/product/";
$productName;
$productDescription;
$productImages = [];
$productPrice;
$productLink;
$productItemId;
$productShopId;
$productThumbnail;
$productStock;
$productRating;
$productCategory;
$productReviewCount;
$productBrand;
$productAffiliateLink = "";
$getTotalKeywords = array();
$errorCode = array();


// if post generate
if (isset($_POST["action"])) {
    switch ($_POST["action"]) {
        case "generate":
            if (isset($_POST["keywords"])) {
                // ensure users is add keywords
                if (isset($_POST['aff_link'])) {
                    $productAffiliateLink = $_POST['aff_link'];
                } else {
                    $productAffiliateLink = "/shop";
                }
                getbyKeywords($_POST["keywords"]);
            }
           
            break;
        case "gettrending":
            getTrending();
            break;
        case "update_aff_link":
          if (isset($_POST['new_aff_link']) && isset($_POST['product_id'])) {
              $product_id = $_POST['product_id'];
              updateAffLink($_POST['new_aff_link'], $product_id);
          }
           
        break;
    }
}
// function update affiliate link
function updateAffLink($link, $id)
{
    global $conn;
    $sql = "UPDATE products SET product_aff_link='$link' WHERE id=$id";
    if ($conn->query($sql) === true) {
        echo json_encode(array(
            "status" => "success",
            "update_link" => "$link",
        ));
    } else {
        echo "Error updating link: " . $conn->error;
    }
}


if (isset($_GET["cron"])) {
    switch ($_GET["cron"]) {
        case "auto":
            getTrending();
            break;
    }
}

// get keywords from database
function getbyKeywords($keywords) {   
    global $productGenerated;
    global $productSkipped;
    global $getTotalKeywords;
    global $api_url;
    global $errorCode;
    $keywords = json_decode($keywords);
    foreach ($keywords as $keyword) {
        $keyword = preg_replace("/\s+/", "", $keyword);
        $productgrabtype = $api_url."?action=get&keywords=".$keyword;
        $products = grabShopee($productgrabtype);

        if(json_decode($products)->status == 'failed') {
            array_push($errorCode, 'Curl Failed');
        } else {
            // do curl
            array_push($getTotalKeywords, $keyword);
                    
            connectShopee($products);
 
        }
        
       
    }
    if(count($productGenerated) > 0) {
        echo json_encode(array(
            "status" => "success",
            "success_code" => "Success Generated ".count($productGenerated)." Products"
        ));
    }
   
    
    else {
        if(count($productSkipped) > 0) {
            echo json_encode(array(
                "status" => "success",
                "success_code" => "Skipped ".count($productSkipped)." Products "
            ));
        }
        else {
            if($keyword == null) {
                echo json_encode(array(
                    "status" => "failed",
                    "success_code" => "No Keywords Inputed",
                ));
            }
            if(count($errorCode) > 0) {
                echo json_encode(array(
                    "status" => "failed",
                    "success_code" => "Maybe Curl have problem",
                ));
            }
            
        }
        
       
    }
}

// curl grab to shopee

function grabShopee($url)
{
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_ENCODING, "gzip");
    curl_setopt ($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
    $resp = curl_exec($curl);
    if(curl_errno($curl)) {
        return json_encode(array(
            "status" => "failed",
            "success_code" => "Curl Failed: " . curl_error($curl),
        ));
    }
    else {
        return $resp;
    }
}


// function get trending search
function getTrending()
{
    global $api_url;
    $trends = grabShopee(
        $api_url."?action=gettrending"
    );
    $trends = json_decode($trends);
    $trends = $trends->data->querys;
    foreach ($trends as $trend) {
        $trend = $trend->text;
        $trend = preg_replace("/\s+/", "", $trend);
        $productgrabtype = $api_url."?action=get&keywords=".$trend;
        $products = grabShopee($productgrabtype);
        connectShopee($products);
    }
}
// get url path
function getProductUrl($productName, $itemid, $shopid)
{
    // replace productname to url
    $url = preg_replace("/[\*\?\#\&\/\+\!\---\[\]\|\.\,\%\”\"\(\)\s+\/\@\\\\]/", "-", $productName);
    $url = preg_replace("/\-{2,}/", "-", $url);
    $url = strtolower($url);
    return $url;
}

// get images path
function getProductImageUrl($image)
{
    $imageurl = "https://cf.shopee.co.id/file/{$image}";
    return $imageurl;
}

// get product price
function getProductPrice($price)
{
    $price = intval($price) / 100000; // divide by 1000 cause shopee returned billion price of real price
    // thousand separator
    return $price;
}

//grabbing
function connectShopee($products)
{
    global $productGenerated;
    global $productSkipped;
    global $errorCode;
    global $getTotalKeywords;
    global $conn;
    global $api_url;
    global $productAffiliateLink;
    if (json_decode($products)->items != null) {
        $products = json_decode($products)->items;
        foreach ($products as $product) {
            $productName = $product->item_basic->name;
            if (str_contains($productName, "?")) {
                $productName = str_replace("?", "", $productName);
            }
            $productItemId = $product->item_basic->itemid;
            $productShopId = $product->item_basic->shopid;
            $productImages = $product->item_basic->images;
            $productImages = json_encode($productImages);
            $productThumbnail = $product->item_basic->image;
            $productLink = getProductUrl(
                $productName,
                $productItemId,
                $productShopId
            );
            $productPrice = $product->item_basic->price;
            $productPrice = getProductPrice($productPrice);
            // next curl to get product description
            $grabProduct = grabShopee(
                $api_url."?action=getproductdata&product_item_id=".$productItemId."&product_shop_id=".$productShopId
            );
            $grabProduct = json_decode($grabProduct);
            $productDescription = $grabProduct->data->description;
            $productStock = $grabProduct->data->stock;
            $productRating = $grabProduct->data->item_rating->rating_star;
            $productCategory = $grabProduct->data->categories[0]->display_name;
            $productReviewCount = $grabProduct->data->item_rating->rating_count[0];
            $productBrand = $grabProduct->data->brand;
            $productBrand = $productBrand != null ? $productBrand : "Unknown";

            if ($productDescription != null) {
                $productDescription = $productDescription;
            } else {
                $productDescription = "";
            }
            /*
            echo $productName."<br><br>";
            echo getProductUrl($productName, $productItemId, $productShopId)."<br><br>";
            echo getProductImageUrl($productImages[0])."<br><br>";
            echo getProductImageUrl($productImages[0])."<br><br>";
            echo getProductPrice($productPrice)."<br><br>";
            echo $productDescription."<br><br>";
            */
            $productDescription = htmlentities(
                $productDescription,
                ENT_QUOTES,
                "UTF-8"
            );

            // submitting to sql server
            $user_check_query = "SELECT * FROM products WHERE product_itemid='$productItemId' OR product_shopid='$productShopId' LIMIT 1";
            $result = mysqli_query($conn, $user_check_query);
            $isProductExist = mysqli_fetch_assoc($result);

            if (!$isProductExist && $productDescription != null &&
            strlen($productName) > 22 &&
            !str_contains($productDescription, "????????????????????????") &&
            $productReviewCount > 1) {
                // if product not exist
                // filter if description null
                // filter if product name < 22 character

                // convert to webp before store it
                $thumbnail_url = getProductImageUrl($productThumbnail);
                $filename = $productThumbnail;
                convertImageToWebP(
                    $thumbnail_url,
                    $filename
                );
                
                $localThumbnail = "/assets/images/".$filename.".webp";

                $query = "INSERT INTO products (product_name, product_description, product_images, product_price, product_link, product_itemid, product_shopid, product_thumbnail, product_stock, product_rating, product_category, product_review_count, product_brand, product_aff_link) 
                VALUES('$productName','$productDescription', '$productImages', '$productPrice', '$productLink', '$productItemId', '$productShopId', '$localThumbnail', '$productStock', '$productRating', '$productCategory', '$productReviewCount', '$productBrand', '$productAffiliateLink')";
                /* change character set to utf8 */
                if (!$conn->set_charset("utf8")) {
                } else {
                    $conn->character_set_name();
                }
                if ($conn->query($query) === true) {
                   array_push($productGenerated, $productName);
                }
            } else {
                // skipped products
                array_push($productSkipped, $productName);
            }

            
        }
        
    }
}
