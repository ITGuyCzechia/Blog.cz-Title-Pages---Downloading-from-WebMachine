<?php
include_once 'dbh.inc.php'; // Connecting on choosed database
$errorreached = 0;

class SliderItem {
  public $article;
  public $blog;
  public $title;
  public $description;
  public $source;
  public $date;
  public $image;
  public $turn;
  
  function set_article($article) {
    $this->article = $article;
  }
  
  function get_article() {
    return $this->article;
  }
  
  function set_blog($blog) {
    $this->blog = $blog;
  }
  
  function set_title($title) {
    $this->title = $title;
  }
  
  function set_description($description) {
    $this->description = $description;
  }
  
  function set_source($source) {
    $this->source = $source;
  }
  
  function set_date($date) {
    $this->date = $date;
  }
  
  function set_image($image) {
    $this->image = $image;
  }
  
  function set_turn($turn) {
    $this->turn = $turn;
  }
  
  function print(){
    echo "<b>Article:   ".$this->article."</b><br>";
    echo "<b>Blog:  </b>".$this->blog."<br>";
    echo "<b>Title: </b>".$this->title."    |  ";
    echo "<b>Description: </b>".$this->description."<br>";
    echo "<b>Source:    </b>".$this->source."    |  ";
    echo "<b>Date:  </b>".$this->date."    |  ";
    echo "<b>Turn:  </b>".$this->turn."<br>";
    echo "<b>Image: </b> ".$this->image."<br><img src='".$this->image."' height='50'><br>";
  }
  
  function sql_insert(){
    return "INSERT INTO blogcztitulk9893.titulky 
    (`article`, `blog`, `title`, `description`, `source`, `date`, `turn`, `image`) 
    VALUES ('".$this->article."','".$this->blog."','".$this->title."','
    ".$this->description."','".$this->source."','".$this->date."','".$this->turn."','".$this->image."');";
  } 
}

// Loading Timecode of Page
$t = $_GET['t']; 

// If timecode is not entered, 
// It will start from beginning. (20121227205334)
if(empty($t)){
    $t = '20121227205334'; // 27th December 2012
}

function dateFromTimecode($timecode){
    $year   = substr($timecode, 0, 4);
    $mouth  = substr($timecode, 4, 2);
    $day    = substr($timecode, 6, 2);
    
    $string = $day.". ".$mouth.". ".$year;
    
    return $string;
}

function loadPage($url){
    // Request will be send with option ignore_errors.
    // WebMachine archives even 404 error page with error. 
    // Function file_get_contents with 404 error returns 'false' instead page. 
    // In this case, we still want timecode of next page. 
    // This option will show page and with next page timecode instead.
    $options = [
        'http' => [
            'ignore_errors' => true
        ],
    ];
    $context = stream_context_create($options);
    
    $page = file_get_contents($url, false, $context);
    
    if(empty($page)){
        echo "<b>Error:</b> Page <a href='".$url."'>".$url."</a> was not loaded.<br>";
        echo "HTTP Response Header: <br>";
        print_r($http_response_header);
        echo "<br>";
        global $errorreached;
        $errorreached = $errorreached + 1;
        return "";
    }
    
    return $page;
}

function takePartBetween($from, $start, $end){
    $FirstCut = explode($start, $from);
    if(count($FirstCut) < 2){ // If start was not at string
        return "";
    }else{
        $OtherCut = explode($end, $FirstCut[1]);
        return $OtherCut[0];
    }
}

// Take data
// Load them to database

function changeWebMachineLinksToOriginal($link){
    if(str_contains($link, '/http')){
        // Will cut https://web.archive.org/web/20190516200510/http <- Here ://blog.cz/
        $Cut = explode('/http', $link, 2);
        $shorterLink = "http".$Cut[1];
        return $shorterLink;
    }
    return $link;
}

function takeArticle($item){
     return takePartBetween($item, '<a href="', '">');
}

function takeTitle($item){
     return takePartBetween($item, '<span class="title">', '</span>
                    <span');
}

function takeDescription($item){
     return takePartBetween($item, '<span class="description">', '</span>');
}

function takeImage($item){
     return takePartBetween($item, '<img src="', '" alt="');
}

function takeBlog($article){
     return "http".takePartBetween($article, 'http', 'blog.cz')."blog.cz/";
}

function sliderToItems($wholeSliderAsString){
    $objects = array();
    $turn = 0;
    while(str_contains($wholeSliderAsString, '</li>')){
        $turn++;
        $FirstCut = explode('<li>', $wholeSliderAsString, 2);
        $OtherCut = explode('</li>', $FirstCut[1], 2);
        $wholeSliderAsString = $OtherCut[1];
        $item =  $OtherCut[0];
        $object = new SliderItem();
        $object->set_turn($turn);
        $object->set_article(changeWebMachineLinksToOriginal(takeArticle($item)));
        $object->set_title(takeTitle($item));
        $object->set_description(takeDescription($item));
        $object->set_image(takeImage($item));
        $object->set_blog(takeBlog($object->get_article()));
        global $url;
        $object->set_source($url);
        global $date;
        $object->set_date($date);
        array_push($objects, $object);
    }
    
    return $objects;
}

function takeSlider($page){
    $wholeSliderAsString = takePartBetween($page, '<ul id="carouselList">', '</ul>');
    $return = sliderToItems($wholeSliderAsString);
    return $return;
}

function takeNextPage($page){
    $tr_d = takePartBetween($page, '<tr class="d">', '</tr>');
    $link = takePartBetween($tr_d, '<td class="f" nowrap="nowrap"><a href="', '" title="');
    $result = takePartBetween($link, 'web.archive.org/web/', '/');
    return $result;
}

function takeJustTimeFromWebMachineLink($link){
    return takePartBetween($from, "https://web.archive.org/web/", "/h");
}

function checkOfRedundation($conn, $object){
    global $conn;
    $result = false;
    $select_sql = "SELECT article, title FROM blogcztitulk9893.titulky 
                   WHERE article='".$object->article."' AND title='".$object->title."';";
    $select_result = mysqli_query($conn, $select_sql);
    $resultCheck = mysqli_num_rows($select_result);
    
    if($resultCheck == 0){
        $result = true;
    }else{
        echo "<p style='color: blue'>Record (<b style='color: lightblue'>$object->title</b>) was not saved, because it is already in database.</p>";
    }
    
    return $result;
}

function putToDatabase($arrayObjects){
    global $conn;
    foreach ($arrayObjects as &$object) {
        if(checkOfRedundation($conn, $object)){ // Check if title isn't on database.
            $sql = $object->sql_insert();
            $errorstatus = mysqli_query($conn, $sql);
            $object->print();
            if($errorstatus != 1){
                echo "<b>Error (".$errorstatus.")</b>: Page couldn't upload data to database. Unupload data was<br>";
                $object->print;
                global $errorreached;
                $errorreached = $errorreached + 1;
            }else{
                echo "<b><span style='color: green'>✅ Success - Data was uploaded to database.</span></b><br><br>";
            }
        }
    }
}

// Function for printing for debugging and so on.
function printObjects($arrayObjects){
    foreach ($arrayObjects as &$object) {
        $object->print();
    }
}

function translateMouth($string){
    $en = [ 'Jan', 'Feb', 'Mar', 
            'Apr', 'May', 'Jun', 
            'Jul', 'Aug', 'Sep', 
            'Oct', 'Nov', 'Dec',];
    $cz = [ 'ledna', 'února', 'března', 
            'dubna', 'května', 'června', 
            'července', 'srpna', 'záři', 
            'října', 'listopadu', 'prosince'];
    return str_replace($en, $cz, $string);
}

function returnDate($page){
    $firstCut  = takePartBetween($page, '<!-- NEXT/PREV CAPTURE NAV AND DAY OF MONTH INDICATOR -->
          <tr class="d">', '</tr>');
    $secondCut = takePartBetween($firstCut,  '<td class="c"', '</td>');
    $thirdCut  = takePartBetween($secondCut, 'You are here: ',   '">');
    $dateEN = substr($thirdCut, 9);
    $mouth = substr($dateEN, 0, 3);
    $year  = substr($dateEN, -4, 4);
    $day   = substr($dateEN, 4, 2);
    $DateBeta = $day.". ".$mouth." ".$year;
    return translateMouth($DateBeta);
}

function redirect($nextPageTimecode){
    global $t;
    $now = intval($t);
    $next = intval($nextPageTimecode);
    
    $url="http://blogcztitulky.wz.cz/loading.php?t=".$nextPageTimecode;
    
    if(empty($nextPageTimecode) or (strcmp($nextPageTimecode, "") == 0)){
        echo "Next page was not found. It's no page to redirect you.";
    }else{
        if($next <= $now){ // If next Timecode is wrong, we will next each day
            $nextPageTimecode = $now + 1000000; // N. 1000000 is for 1 day 
            echo "Warning: We need to try different nextpage.<br>";
        }
        echo "<script>function next() {console.log('Redirect'); 
                window.location.href='".$url."'}; 
                setTimeout(next, 2000); </script>";
    }
}

$url  = "https://web.archive.org/web/".$t."/http://blog.cz:80/";
$page = loadPage($url);
$date = returnDate($page);

echo '<html lang="cs"><body>';
    
echo "Pagecode: <a href='https://web.archive.org/web/".$t."/http://www.blog.cz/'>".$t."</a> (Data are from this page)<br>";

$arrayObjects = takeSlider($page);
putToDatabase($arrayObjects);
$nextPageTimecode = takeNextPage($page);
echo "Next page is:  <a href='https://web.archive.org/web/".$nextPageTimecode."/http://www.blog.cz/'>".$nextPageTimecode."</a> <br>";
redirect($nextPageTimecode);
echo '</body></html>';

?>
