<?php
namespace App\Http\Controllers\Bot;

use App\Http\Controllers\Controller;
use App\Mail\Resume;
use Illuminate\Support\Facades\Mail;
use Telegram;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Exceptions\TelegramOtherException;
//use Telegram\Bot\Objects\InlineQuery\InlineQueryResultPhoto;
use mtgsdk\Card;
use Symfony\Component\DomCrawler\Crawler;
use Telegram\Bot\Objects\Message;

class CommandHandlerController extends Controller
{
    public function webhook()
    {
  try {
            /**
             * @var $updates Telegram\Bot\Objects\Update
             * @var $update Telegram\Bot\Objects\Update
             */
            $update = Telegram::commandsHandler(true);
//            $updates = Telegram::getWebhookUpdates();
//            Telegram::sendMessage([
//                'parse_mode' => 'Markdown',
//                'chat_id' => '144068960',
//                'text' => "*CommandHandlerController (update):*\r\n" .
//                    '```text ' .
//                    json_encode($updates) .
//                    '```'
//            ]);

//           throw new TelegramOtherException('Essa opção está em desenvolvimento no momento. Tente novamente outro dia. COMANDSHANDLER');
            $callbackQuery = $update->get('callback_query');
            $message = $update->getMessage();
						$inlineQuery = $update->getInlineQuery();
						$updateArray = json_decode(json_encode($update), true);
						//inline mode
						if($message && substr( $message->getText(), 0, 1 ) !== "/" && array_key_exists('inline_query', $updateArray)) {
							//$this->alertOwner(self::array2ul($inlineQuery));
							$query = $inlineQuery->getQuery();
							//omoImage
							$cards = Card::where(['name' => $query])->all();
							$results = [];
							if (!empty($cards)) {
								foreach($cards as $card) {
								if(isset($card->imageUrl)){
									//$desc = $this->getPriceLigaMagic($card->name);
									$results[] = [
										'type' => 'photo',
										'id' => rand(0,989865),
										'photo_url' => $card->imageUrl,
										'thumb_url' => $card->imageUrl,
										'title' => $card->name,
										//'description' => (isset($card->text) ? $card->text : ''),
										//'caption' => (!empty($desc) ? $desc : '...'),
										'parse_mode' => 'Markdown'
									];
								}
							}
							}
							
							//$card = $this->getLigaMagicImage($query);
// 							if ($card) {
								
// 							}
								$response = Telegram::post('answerInlineQuery', [
								'inline_query_id' => $inlineQuery->getId(),
								'results' => json_encode($results)
							]);
							return new Message($response->getDecodedBody());
						}
					
            if ($callbackQuery) {
                $arguments = explode(' ', $callbackQuery->get('data'));
                $command = array_shift($arguments);
                $command = str_replace(['\/', '/'], '', $command);
                $arguments = implode(' ', $arguments);
 
                return Telegram::getCommandBus()->execute($command, $arguments, $callbackQuery);
            }
						//$this->alertOwner('TEXT: '.$message->getText());
            if ($message) {
                $msgTxt = trim($message->getText());
								if(!empty($msgTxt) && substr( $message->getText(), 0, 1 ) !== "/") {
									return Telegram::getCommandBus()->execute('cards', $msgTxt, $update);
								}
                $newMember = $message->getNewChatParticipant();
                if ($newMember && substr( $message->getText(), 0, 1 ) !== "/") {
                    $name = $newMember->getFirstName();
                    return Telegram::getCommandBus()->execute('start', $name, $update);
                }
                $replyToMessage = $message->getReplyToMessage();
                if ($replyToMessage && strpos($replyToMessage, '[\/') !== false) {
                    preg_match("/\[[^\]]*\]/", $replyToMessage->getText(), $matches);
                    $cmd = str_replace(['[', ']'], '', $matches[0]);
                    if ($cmd) {
                        $arguments = explode(' ', $cmd);
                        $command = array_shift($arguments);
                        $command = str_replace(['\/', '/'], '', $command);
                        $text = $message->getText();
                        $text = str_replace(' ', '', $text);
                        if (!ctype_digit($text)) {
                            $text = $message->getText();
                        }
                        $arguments = implode(' ', $arguments) . ' ' . $text;
                        return Telegram::getCommandBus()->execute($command, $arguments, $update);
                    }
                }
// 								if(strpos($message->getText(), '/') !== false) {
// 									$args = $message->getText();
// 									$args = explode(' ', $args);
// 									$cmd = array_shift($args);
// 									$cmd = str_replace(['\/', '/'], '', $cmd);
// 									return Telegram::getCommandBus()->execute($cmd, implode(' ', $args), $update);
// 								}
            }
        } catch (\Exception $e) {
						$this->alertOwner('*DEU ERRO:*' .
                    '```text ' .
                    json_encode($e->getMessage()) . "\r\n" .
                    json_encode($e->getLine()) . "\r\n" .
                    json_encode($e->getFile()) . "\r\n" .
                    '```');
            Log::info('CMHND-ERRO1: ' . $e);
            Log::info('CMHND-ERRO2: ' . json_encode($e->getTrace()));
        }

        return 'ok';
    }

    public static function array2ul($array)
    {
        if (!is_array($array)) $array = json_decode(json_encode($array), true);
        $out = '';
        foreach ($array as $key => $elem) {
            if (!is_array($elem)) {
                $out .= "`$key:` $elem\r\n";
            } else $out .= "```$key:```" . self::array2ul($elem);
        }
        return $out;
    }
	
		public function alertOwner($message) {
			Telegram::sendMessage([
					'parse_mode' => 'Markdown',
					'chat_id' => '144068960',
					'text' => '*ALERT:*' . "\r\n" .
							self::array2ul([$message])
			]);
		}
	
		public function getLigaMagicImage($name) {
			$response = [];
        $resposta = $this->simpleCurl('https://www.ligamagic.com.br/?view=cards%2Fsearch&card='. urlencode($name));
        if ($resposta) {
            $resposta = explode('<html>', $resposta);
            $resposta = '<html>' . $resposta[1];

            $crawler = new Crawler($resposta);
            $node = $crawler->filter('#omoImage img');
						if (!empty($node->count())) {
							return $node->attr('src');
						}
        }
			return null;
		}
	 /**
     * Simple Curl
     *
     * @param string $url URL do request
     *
     * @return mixed
     */
    protected function simpleCurl($url)
    {
        // Get cURL resource
        $curl = curl_init();
        // Set some options - we are passing in a useragent too here
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $url,
            CURLOPT_USERAGENT => 'Codular Sample cURL Request'
        ]);
        // Send the request & save response to $resp
        $resp = curl_exec($curl);
        // Close request to clear up some resources
        curl_close($curl);
        return $resp;
    }
	
	public function getPriceLigaMagic($name) {
		$resposta = $this->simpleCurl('https://www.ligamagic.com.br/?view=cards%2Fsearch&card='. urlencode($name));
        if ($resposta) {
            $resposta = explode('<html>', $resposta);
            $resposta = '<html>' . $resposta[1];

            $crawler = new Crawler($resposta);
            $table = $crawler->filter('.tabela-card');
						if(!empty($table->count())) {
							$table->filter('tr')->each(function (Crawler $node, $i) use (&$response) {
                $title = $node->filter('td')->first()->filter('img')->first()->attr('title');
                $title = explode('/', $title);
                $response[trim($title[0])] = [
                    trim($node->filter('td')->eq(1)->text()),
                    trim($node->filter('td')->eq(2)->text()),
                    trim($node->filter('td')->eq(3)->text()),
                ];
            });
            //unset($response[0]);
          $precos = '';
            foreach($response as $key => $linha) {
              $precos .= $key.":(".implode(' | ', $linha).") ";
            }
            return "Prices in LigaMagic: ".$precos;
						}
					


            
        }
		return null;
	}
}