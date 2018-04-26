<?php
// lastfm2rss 0.0.2

// You must include an api key
$api_key = '';
// Optionally set the default Last.fm username and real name
$user_id = '';
$user_full_name = '';
$max_results = 50;
// Uncomment to set default to loved
// $type = 'loved';

// override default user
if (isset($_GET['user'])) {
	$user_id = $_GET['user'];
	if(isset($_GET['name'])) $user_full_name = $_GET['name'];
	else $user_full_name = null;
}

// override default type
if (isset($_GET['loved'])) $type = 'loved';
elseif (isset($_GET['recent'])) $type = 'recent';
elseif ($type == '') $type = 'recent';

if ($user_id == '' || $api_key == '') die ('Please set parameters');
if ($user_full_name == '') $user_full_name = $user_id;

$postfields = array(
	"method" => 'user.get'.$type.'tracks',
	"user" => $user_id,
	"api_key" => $api_key,
	"limit" => $max_results,
	"format" => "json"
);

$ch = curl_init();    
curl_setopt($ch, CURLOPT_URL, 'https://ws.audioscrobbler.com/2.0');
// curl_setopt($OAcURL, CURLOPT_HTTPHEADER, array('Content-type: application/x-www-form-urlencoded;charset=UTF-8'));
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postfields));
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLINFO_HEADER_OUT, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$info = curl_getinfo($ch);
$error = curl_error($ch);
$errno = curl_errno($ch);

curl_close($ch);

if($code != 200){

	echo 'Error' . PHP_EOL;
	echo $code . PHP_EOL;
	print_r($response);
	print_r($info);

} else {

	$all = json_decode($response, TRUE);

	if ($type == 'loved') { 
		$title = 'Last.fm Recently Loved tracks for ' . $user_full_name;
		$updated = date(DATE_ATOM, $all['lovedtracks']['track'][0]['date']['uts']);
		$link = 'https://www.last.fm/user/' . $user_id . '/loved';
	} elseif ($type == 'recent') {
		$title = 'Last.fm Recently Listened tracks for ' . $user_full_name;
		$updated = date(DATE_ATOM, $all['recenttracks']['track'][0]['date']['uts']);
		$link = 'https://www.last.fm/user/' . $user_id;
	}

	// Start the output
	header("Content-Type: application/rss+xml");
	header("Content-type: text/xml; charset=utf-8");
	?>

	<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
		<channel>
			<lastBuildDate><?php echo $updated ?></lastBuildDate>
			<language>en</language>
			<title><?php echo $title ?></title>
			<description><?php echo $title ?></description>
			<link><?php echo $link ?></link>
			<ttl>960</ttl>
	<?php

	$i = 0;
	foreach($all[$type.'tracks']['track'] as $item) {
		$id = $item['date']['uts'];
		$url = htmlspecialchars($item['url']);
		$published = date(DATE_ATOM, $item['date']['uts']);
		$updated = date(DATE_ATOM, $item['date']['uts']);

		// Create title
		$post_title = '';
		$post_title .= $item['artist']['name'];
		$post_title .= ' - ' . $item['name'];

		// Get only the first line of the title
		$post_title = explode("\n", $post_title);
		$post_title = $post_title[0];      
		// Shorten title if it's too long
		$max_characters = 255;
		if (strlen($post_title)>$max_characters) {
			preg_match('/(.{' . $max_characters . '}.*?)\b/', $post_title, $matches);
			if ( strlen(rtrim($matches[1])) < strlen($post_title) ) {
				$post_title = rtrim($matches[1]) . "...";
			}
		}

		$post_title = htmlspecialchars($post_title);

		// Create Content
		$post_content = '';
		$post_content .= '<p><a href="'.$url.'">' . $item['artist']['name'];
		if (isset($item['album']['name'])) {
			$post_content .= ' - ' . $item['album']['name'];
		}
		$post_content .= ' - ' . $item['name'] . '</a></p>'."\n";
		if ($item['image'][2]) {
			$post_content .= '<p><a href="'.$url.'"><img src="' . $item['image'][2]['#text'] . '" /></a></p>'."\n";
		} else if ($item['image'][3]['#text']) {
			$post_content .= '<p><a href="'.$url.'"><img src="' . $item['image'][3]['#text'] . '" /></a></p>'."\n";
		} else if ($item['image'][1]['#text']) {
			$post_content .= '<p><a href="'.$url.'"><img src="' . $item['image'][1]['#text'] . '" /></a></p>'."\n";
		} else if ($item['image'][0]['#text']) {
			$post_content .= '<p><a href="'.$url.'"><img src="' . $item['image'][0]['#text'] . '" /></a></p>'."\n";        
		}
		//wrapping the content in CDATA can avoid some XML character set problems.
		$post_content = '<![CDATA['.$post_content.']]>';
		?>
			<item>
				<title><?php echo $post_title ?> </title>
				<pubDate><?php echo $published; ?></pubDate>
				<link><?php echo $url ?></link>
				<guid isPermaLink="false"><?php echo $url ?></guid>
				<description><?php echo $post_content?>></description>
			</item>

		<?php
		$i++;
		}
		?>
		</channel>
	</rss>
<?php
}
?>
