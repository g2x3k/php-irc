<?php

/**
 * Created by PhpStorm.
 * User: g2x3k
 * Date: 21/7/2015
 * Time: 03:10
 */
class github_mod extends module
{

    public $title = "github.issue mod";
    public $author = "g2x3k";
    public $version = "0.1";
    private $delay = 0;

    /*
     * Todo:
     * Watch for new issues ? (yes github have service but this more fun)
     */


    public function init()
    {
        // define what github repo to watch
        $this->ghcfg["repo"] = "g2x3k/php-irc";
    }

    public function priv_showIssue($line, $args)
    {
        $channel = strtolower($line['to']);
        $nick = $line['fromNick'];
        // !issue [issue_num] [repo]
        $issuenum = ($args["nargs"] > 0 ? $args['arg1'] : null);
        $repo = ($args["nargs"] > 1 ? $args['arg2'] : $this->ghcfg["repo"]);

        if ($issuenum) {
            // list issue
            $url = 'https://api.github.com/repos/' . str_replace('%2F', '/', urlencode($repo)) . '/issues/' . $issuenum;

            $data = get_url_contents($url);
            $data = json_decode($data["html"]);

            if (empty($data)) {
                //$this->bot->say('Fetching issue failed');
                $this->ircClass->privMsg($channel, "Fetching failed, tried getting issue $issuenum from $repo");
                return;
            }

            $this->ircClass->privMsg($channel, (isset($data->pull_request) ? 'Pull request' : 'Issue') . ' #' . $data->number . ': ' . $data->title . ' (' . $data->comments . ' comment(s))');
            $this->ircClass->privMsg($channel, 'Reported by ' . $data->user->login . ', current status: ' . $data->state);
            $this->ircClass->privMsg($channel, substr(preg_replace('/\s\s+/', ' ', $data->body), 0, 250));
            $this->ircClass->privMsg($channel, 'More information: ' . $data->html_url);

        } else {

            $url = 'https://api.github.com/repos/' . str_replace('%2F', '/', urlencode($repo)) . '/issues';

            $data = get_url_contents($url);
            $result = json_decode($data["html"]);

            $i = 0;
            if (count($result)) {
                $this->ircClass->privMsg($channel, "Sending last few issues for $repo in privmsg");

                foreach ($result as $issue) {
                    if ($i >= 5)
                        break;

                    $this->ircClass->privMsg($nick, (isset($issue->pull_request) ? '[Pull]' : '[Issue]') . ' #' . $issue->number . ': ' . $issue->title . ' (reported by ' . $issue->user->login . ', status: ' . $issue->state . ') <' . $issue->html_url . '>');

                    $i++;
                }
            }
            else
                $this->ircClass->privMsg($channel, "no issues to display for $repo");
        }
    }
}

?>