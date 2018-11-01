

if (!empty($_POST['ok_posts_ids']) && !empty($_SESSION["ok_access_token"])) {
for ($i = 0; $i <= count($ok_posts_ids); $i += 100) {
  $groups = [];
  try{
    $params = array("media_topic.ID",
                    "group.NAME",
                    "group.MEMBERS_COUNT",
                    "group.UID",
                    "media_topic.AUTHOR_REF",
                    "media_topic.OWNER_REF",
                    "media_topic.CREATED_MS",
                    "media_topic.DISCUSSION_SUMMARY",
                    "media_topic.LIKE_SUMMARY",
                    "media_topic.RESHARE_SUMMARY",
                    "media_topic.VIEWS_COUNT"
    );

    $r = $ok->makeRequest($_SESSION['ok_access_token'],"mediatopic.getByIds", array(
        'topic_ids' => implode(",",array_slice($ok_posts_ids,$i,100)),
        'media_limit' => 3,
        'fields' => implode(",",$params)
    ));

    $group_params = array("name",
                    "members_count",
                    "UID"
    );
    $ids = array_column($r['entities']['groups'], 'uid');
    $groups = array_unique($ids);

    $f = $ok->makeRequest($_SESSION['ok_access_token'],"group.getInfo", array(
        'uids' => implode(",",$groups),
        'fields' => implode(",",$group_params)
    ));

      foreach ($ok_posts_ids as $value) {

        $id = searchForId($value,$r['media_topics']);
        if (is_null($id)) continue;

        $media_topic = $r['media_topics'][$id];

        $subscribers = 0;
        $owner_id = getNumbers($media_topic["owner_ref"]);
        $owner_url = "https://ok.ru/group/".$owner_id;

        $group = [];
        $group = search_array($f,'uid',$owner_id);
        $name = $group['name'];
        $subscribers = $group['members_count'];

        array_push($ok_stats,array(
          "#" => 0,
          "site" => "OK",
          "owner_id" => strval($owner_id),
          "post_id" =>  strval($media_topic["id"]),
          "signer_id" => $media_topic["author_ref"],
          "is_admin" => 0,
          "source" => "Группа",
          "name" => $name,
          "owner_url" => $owner_url,
          "members" => $subscribers,
          "url" => $owner_url."/topic/".$media_topic["id"],
          "date" =>  date("d.m.y H:m",$media_topic["created_ms"]/1000),
          "comments" =>  $media_topic["discussion_summary"]["comments_count"],
          "likes" =>  $media_topic["like_summary"]["count"],
          "reposts" =>  $media_topic["reshare_summary"]["count"],
          "views" =>  (isset($media_topic["views_count"])) ? $media_topic["views_count"] : "Нет данных",
          "reach" =>  "Нет данных"
        ));
      }
  }
  catch (Exception $e){

  }
  sleep(1);
}
}

if ($_POST["reach"] == "true" && count($ok_stats)>0 && isset($_SESSION['ok_access_token'])) {
  //OK REACH
  foreach ($ok_stats as &$value) {
    try{
      $params = array("reach");

      $r = $ok->makeRequest($_SESSION['ok_access_token'],"group.getStatTopic", array(
          'topic_id' => $value['post_id'],
          'fields' => implode(",",$params)
      ));
      if (!isset($r["error_code"]))
        $value["reach"] = $r["topic"]["reach"];
      else $value["reach"] = "Нет данных";
    }
    catch (Exception $e){
      $value["reach"] = "Нет данных";
      sleep(0.5);
    }
    sleep(0.5);

  }
}
