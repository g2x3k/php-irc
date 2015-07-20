<?
class joke_mod extends module
{
    public $title = "simple joke trigger";
    public $author = "g2x3k";
    public $version = "0.1";
    private $delay = 0;

    public function init()
    {
        //nothing
    }

    public function priv_joke($line, $args)
    {
        $channel = $line["to"];
        $nick = $line["fromNick"];

        $data = get_url_contents("http://api.icndb.com/jokes/random");
        $data = $data["html"];
        // ICNDB has escaped slashes in JSON response.
        $data = stripslashes($data);
        $joke = json_decode($data);
        if ($joke) {
            if (isset($joke->value->joke)) {
                $this->ircClass->privMsg($channel, html_entity_decode($joke->value->joke));
            }
        }
        else
        $this->ircClass->privMsg($channel, "I don't feel like laughing today. :(");

    }
}

?>