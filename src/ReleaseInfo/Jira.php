<?php
namespace TheRat\SymDep\ReleaseInfo;

use GuzzleHttp\Client;

class Jira
{
    protected $url;

    protected $credentials;

    /**
     * @var Client
     */
    protected $client;

    public function __construct($url, $credentials)
    {
        $this->url = $url;
        $this->credentials = $credentials;

        $this->client = new Client();
    }

    /**
     * @param array $taskNameList
     * @return Issue[]
     */
    public function generateIssues(array $taskNameList)
    {
        $query = http_build_query(
            [
                'jql' => sprintf('key IN ("%s")', implode('", "', $taskNameList)),
                'startAt' => 0,
                'maxResults' => 100,
            ]
        );
        $request = $this->client->get($this->url.'/search?'.$query);
        $response = $request->getBody()->getContents();
        $response = json_decode($response, true);

        $result = [];
        if (!empty($response['issues'])) {
            foreach ($response['issues'] as $item) {
                $result[] = new Issue(
                    $item['key'],
                    $item['fields']['summary'],
                    $item['self'],
                    $item['fields']['assignee']['name'],
                    $item['fields']['status']['name']
                );
            }
        }

        return $result;
    }

    public function changeStatus(Issue $issue, $releasedStatus)
    {
        $data = [
            "transition" => ['id' => 731],
        ];
        $url = $this->url.'/issue/'.$issue->getName().'/transitions?expand=transitions.fields';
        $request = $this->client->post($url);
        $request->setHeader('Content-type', 'application/json');
        $request->setBody(json_encode($data));
        $response = $request->getBody()->getContents();
        $response = json_decode($response, true);
    }
}
