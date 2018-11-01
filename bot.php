<?php

use VK\Client\VKApiClient;
use VK\Client\VKApiRequest;

// error_reporting(E_ALL);
// ini_set("display_errors",1);

class bot {

  private $vk;
  private $vk_user_access_token = "";
  private $vk_group_access_token = "";


public function __construct($vkgroupaccesstoken) {
    $this->vk = new VKApiClient();
    $this->vk_group_access_token = $vkgroupaccesstoken;
  }

//Рабирает данные которые приходят от VK
public function bot_handleData($data){
  $vk = new VKApiClient();
  $from_id = $data['from_id'];

  $r = [];
  $date_from = date('Y-m-d', strtotime("-1 week"));
  $date_to = date('Y-m-d');

  preg_match_all("/[0123]?\d{1}[.][01]?\d[.]\d{2}/",$data['text'],$r);
  if (count($r[0]) > 0) {
    $date_from = DateTime::createFromFormat('d.m.y', $r[0][0])->format('Y-m-d');

    if (count($r[0]) > 1) {
      $date_to = DateTime::createFromFormat('d.m.y', $r[0][1])->format('Y-m-d');
    } else $date_to = date('Y-m-d',strtotime("+1 week"));
  }

  preg_match_all("/vk.com\/([a-zA-Z0-9\.\_]+)/", $data['text'], $r);
  $vk_screen_names = array_unique($r[1]);

  preg_match_all("/vk.com\/(?:video)(-?[a-zA-Z0-9\.\_]+)/",$data['text'],$r);
  $videos = array_unique($r[1]);

  $isAuth = $this->userisAuth($from_id);

  if (count($vk_screen_names) > 0)
    if ($isAuth) {

      $vk_groups_ids = [];
      $a = $this->vk_resolveScreenNames($vk_screen_names);

      foreach($a as $item){
        if ($item['type'] == 'group' || $item['type'] == 'page')
          array_push($vk_groups_ids, $item);
        }

      $this->bot_sendGroupsStats($from_id, $vk_groups_ids, $date_from, $date_to);
    } else $this->bot_sendAuthNeeded($from_id);

  if (count($videos)>0)
      if ($isAuth) {
        $this->bot_sendVideosStats($from_id, $videos);
      } else $this->bot_sendAuthNeeded($from_id);
}

//Получаем id от Screen name сообщества или человека
function vk_resolveScreenNames($screen_name){
  $result = [];
  for ($i=0; $i < count($screen_name); $i += 10) {
    try {
      $executeData = $this->vk->Execute()->Method($this->vk_user_access_token,'resolveScreenNames', array(
        'screen_names' => implode(",",array_slice($screen_name,$i*10,10)),
      ));
    } catch (Exception $ex){
    }
    $result = array_merge($result,$executeData);
  }
  return $result;
}

//Отправляем статистику о видеозаписях
function bot_sendVideosStats($user_id, $videos) {
    $raw_stat = $this->VideosStats($videos);
    $msg = "Итого видео: {$raw_stat['count']}
Просмотры: {$raw_stat['views']}
Лайки: {$raw_stat['likes']}
Комментарии: {$raw_stat['comments']}
Репосты: {$raw_stat['shares']}";
    $this->messagesSend($user_id, $msg);
}

//Отправляем статистику о группах
function bot_sendGroupsStats($user_id, $vk_groups, $date_from, $date_to) {

    $vk_groups_ids = [];

    foreach ($vk_groups as $value) {
      array_push($vk_groups_ids,$value['object_id']);
    }

    $raw_stat = $this->GroupsStats($vk_groups_ids,$date_from,$date_to);

    $i = 1;

    $groupscount = count($raw_stat);
      foreach ($raw_stat as $group) {

        $group_posts = $this->PostsbyDate($group['id'],$date_from,$date_to);
        $posts_stats = $this->posts_activity_sum($group_posts);
        $posts_count = count($group_posts);

        if ($group['is_admin'] > 0) {
          $msg = "{$i}/{$groupscount} @{$group['screen_name']}({$group['name']}) {$group['date_from']} — {$group['date_to']}

Подписчики: {$group['members_count']}
Количество постов: {$posts_count}

Статистика группы:
Охват: {$group['reach']}
Подписалось: {$group['subscribed']}
Отписалось: {$group['unsubscribed']}
Прирост: {$group['growth']}

Статистика активности:
Лайки: {$group['likes']}
Комментарии: {$group['comments']}
Репосты: {$group['shares']}

Статистика публикаций:
Просмотры: {$posts_stats['views']}
Лайки: {$posts_stats['likes']}
Комментарии: {$posts_stats['comments']}
Репосты: {$posts_stats['shares']}
                  ";
           } else {
             $msg = "{$i}/{$groupscount} @{$group['screen_name']}({$group['name']}) {$group['date_from']} — {$group['date_to']}

Подписчики: {$group['members_count']}
Количество постов: {$posts_count}

Статистика публикаций
Просмотры: {$posts_stats['views']}
Лайки: {$posts_stats['likes']}
Комментарии: {$posts_stats['comments']}
Репосты: {$posts_stats['shares']}";
           }
           $i = $i+1;
           $this->messagesSend($user_id, $msg);
     }
}


//Если пользователь не в системе предложить авторизацию для получения токена на работу с группами пользователя
function bot_sendAuthNeeded($user_id){
  $msg = "Авторизуйтесь на сайте *";
  $this->messagesSend($user_id, $msg);
}

//Отправляем статистику о видеозаписях
function VideosStats($videos)
{
  $result = array('count' => 0,
    'views' => 0,
    'comments' => 0,
    'likes' => 0,
    'shares' => 0,
    'files'=> 0
  );

  $data = [];
  for ($i=0; $i <  count($videos) / 10; $i++) {
    try {
      $executeData = $this->vk->Execute()->Method($this->vk_user_access_token,'VideoStats', array(
        'video_ids' => implode(",",array_slice($videos,$i*10,10)),
        'func_v' => 2,
        'v' => "5.84"
      ));

    } catch (Exception $ex){
    }
    sleep(0.5);
    $result['count'] += $executeData['count'];
    $result['views'] += $executeData['views'];
    $result['comments'] += $executeData['comments'];
    $result['likes'] += $executeData['likes'];
    $result['shares'] += $executeData['shares'];
  }
  return $result;
}

//Отправляем статистику групп
function GroupsStats($vk_groups_ids,$date_from,$date_to){
  $result = [];
  $data = [];
  for ($i=0; $i <  count($vk_groups_ids) / 10; $i++) {
    try {
      $executeData = $this->vk->Execute()->Method($this->vk_user_access_token,'getGroupsStats', array(
        'groups' => implode(",",array_slice($vk_groups_ids,$i*10,10)),
        'date_from' => $date_from,
        'date_to' => $date_to,
        'v' => "5.80"
      ));
      $data = array_merge($data,$executeData);
    } catch (Exception $ex){
      echo $ex->getMessage();
    }
    sleep(0.5);

  }

  if (count($data) > 0){
      foreach ($data as $value) {
        $s = array('name' => $value['name'],
          'id' => $value['id'],
          'screen_name' => $value['screen_name'],
          'members_count' => $value['members_count'],
          'reach' => 0,
          'subscribed' => 0,
          'unsubscribed' => 0,
          'growth' => 0,
          'likes' => 0,
          'comments' => 0,
          'shares' => 0,
          'is_admin' => $value['is_admin'],
          'date_from' => $date_from,
          'date_to' => $date_to
        );

          if ($value['is_admin'] >= 1) {
            foreach ($value['stats'] as $val) {
                $s['reach'] += intval($val['reach']['reach']);

                if (isset($val['activity']['subscribed']))
                  $s['subscribed'] += intval($val['activity']['subscribed']);
                if (isset($val['activity']['unsubscribed']))
                  $s['unsubscribed'] += intval($val['activity']['unsubscribed']);
                if (isset($val['activity']['likes']))
                  $s['likes'] += intval($val['activity']['likes']);
                if (isset($val['activity']['comments']))
                  $s['comments'] += intval($val['activity']['comments']);
                if (isset($val['activity']['copies']))
                  $s['shares'] += intval($val['activity']['copies']);
            }
            $s['growth'] = $s['subscribed'] - $s['unsubscribed'];
          }

          array_push($result, $s);
          }
      }
      return $result;

}

//Проверяем авторизацию польщователя
function userisAuth($user_id){

  $db = new db();
  $user = $db->SearchUser($user_id);

  if (isset($user[0]['id'])) {
      $this->vk_user_access_token = $user[0]['access_token'];

      $vk_user = $this->vk->users()->get($this->vk_user_access_token);
      if (isset($vk_user[0]['id'])){
        return true;
    }
  }
  return false;
}

//Возвращаем статистику о постах за период
function PostsbyDate($vk_group_id,$date_from,$date_to){
  $result = [];
  $ids = [];
    $r = [];

    $group_posts = [];

    $count = 50;
    $offset = 0;
    $search_is_done = false;

    while (!$search_is_done){
      try{
        $executeData = $this->vk->Wall()->Get($this->vk_user_access_token, array(
          'owner_id' => -1*$vk_group_id,
          'count' => $count,
          'offset' => $offset,
          'v' => "5.80"
        ));
        sleep(0.5);

      } catch(Exception $ex){  }
      if (!empty($executeData['items'])) {
      foreach ($executeData['items'] as $post) {
        if (isset($post['is_pinned'])) {
          if (($date_to >= date("Y-m-d",$post["date"])) &&
            ($date_from <= date("Y-m-d",$post["date"])))
          {
                array_push($group_posts,$post);
          }
        } else {

          if ($date_to >= date("Y-m-d",$post["date"])) {
              if ($date_from <= date("Y-m-d",$post["date"])){
                //нашли пост по дате
                array_push($group_posts,$post);
              } else {
                $search_is_done = true;
                break;
              }
          } else {
            continue;
          }
        }
      }
      $offset += 50;
    } else $search_is_done = true;
  }
  return $group_posts;
}


//Поиск в массиве по ключам
function search_byKeyValue($key,$value,$array){
  foreach ($array as $row) {
      if ($value[$key] == $value) return $row;
  }
  return -1;
}


//Возвращает суммы интеракций по постам
function posts_activity_sum($posts){
  $likes = 0;
  $comments = 0;
  $shares = 0;
  $views = 0;

  foreach ($posts as $value) {
    $likes += $value['likes']['count'];
    $comments += $value['comments']['count'];
    $shares += $value['reposts']['count'];
    $views += $value['views']['count'];
  }

  return array('likes' => $likes,
               'comments' => $comments,
               'views' => $views,
               'shares' => $shares);
}

 function messagesSend($user_id, $msg){
   $this->vk->Messages()->Send($this->vk_group_access_token,
                        array('peer_id' => $user_id,
                      'message'=>$msg,
                    'v'=>'5.80'));
 }
}
