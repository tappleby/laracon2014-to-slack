<?php

require_once 'vendor/autoload.php';

use Symfony\Component\DomCrawler\Crawler;


define('SLACK_WEBHOOK_URL', '');
define('SLACK_CHANNEL', '#laracon2014');

function parseEntry($entry) {
	$crawler = new Crawler($entry->html);
	$author = $crawler->filter('.liveblog-author-name')->text();
	$avatar = $crawler->filter('.avatar')->attr('src');
	$content = $crawler->filter('.liveblog-entry-text')->attr('data-original-content');

	return (object)compact('author', 'avatar', 'content');
}

$guzzleClient = new \Guzzle\Http\Client();
$timestampLast = time();

while(true) {
	$timestampCurrent = time();
	$url = "http://live.besnappy.com/laracon-2014/liveblog/{$timestampLast}/{$timestampCurrent}";
	printf("Fetching %s".PHP_EOL, $url);
	$resp = json_decode(file_get_contents($url));

	if ($resp->latest_timestamp) {
		$timestampLast = $resp->latest_timestamp + 1;
		$entries = array_map('parseEntry', $resp->entries);

		$requests = [];
		foreach ($entries as $entry) {
			$payload = json_encode([
				'text' => $entry->content,
				'channel' => SLACK_CHANNEL,
				'username' => $entry->author,
				'icon_url' => $entry->avatar,
				'unfurl_links' => true
			]);

			$requests[] = $guzzleClient->post(SLACK_WEBHOOK_URL, [], compact('payload'));
		}

		if (!empty($requests)) {
			print "Posting to slack".PHP_EOL;
			$guzzleClient->send($requests);
		}
	}

	sleep(10);
}