<?php
namespace Api\Model;
use Api\Model\BaseModel;
/**
 * 
 * @author star7th      
 */
class PageModel extends BaseModel {


    //搜索某个项目下的页面
    public function search($item_id,$keyword){
        $return_pages = array() ;
        $item = D("Item")->where("item_id = '%d' and is_del = 0 ",array($item_id))->find();
        $pages = $this->where("item_id = '$item_id' and is_del = 0")->order(" s_number asc  ")->select();
        if (!empty($pages)) {
          foreach ($pages as $key => &$value) {
            $page_content = $value['page_content'];
            if (strpos( strtolower($item['item_name']."-". $value['page_title']."  ".$page_content) ,strtolower ($keyword) ) !== false) {
              $value['page_content'] = $page_content ;
              $return_pages[] = $value;
            }
          }
        }
        unset($pages);
        return $return_pages;
    }  

    //根据标题更新页面
    //其中cat_name参数特别说明下,传递各格式如 '二级目录/三级目录/四级目录'
    public function update_by_title($item_id,$page_title,$page_content,$cat_name='',$s_number = 99,$author_uid = 0,$author_username='update_by_title'){
        $item_id = intval($item_id);
        $s_number = intval($s_number);
        if (!$item_id) {
          return false;
        }

        // 用路径的形式（比如'二级目录/三级目录/四级目录'）来保存目录信息并返回最后一层目录的id
        $cat_id = D("Catalog")->saveCatPath($cat_name,$item_id);

        $this->cat_name_id[$cat_name] = $cat_id ;
        
        if ($page_content) {
            $page_array = D("Page")->field("page_id")->where(" item_id = '$item_id' and is_del = 0  and cat_id = '$cat_id'  and page_title ='%s' ",array($page_title))->find();
            //如果不存在则新建
            if (!$page_array) {
                $add_data = array(
                    "author_uid"=>$author_uid,
                    "author_username" => $author_username, 
                    "item_id" => $item_id, 
                    "cat_id" => $cat_id, 
                    "page_title" => $this->_htmlspecialchars($page_title) , 
                    "page_content" => $this->_htmlspecialchars($page_content), 
                    "s_number" => $s_number, 
                    "addtime" => time(),
                    );
                $page_id = D("Page")->add($add_data);
            }else{
                $page_id = $page_array['page_id'] ;
                $update_data = array(
                    "author_uid"=>$author_uid,
                    "author_username" => $author_username, 
                    "item_id" => $item_id, 
                    "cat_id" => $cat_id, 
                    "page_title" => $this->_htmlspecialchars($page_title), 
                    "page_content" => $this->_htmlspecialchars($page_content), 
                    "s_number" => $s_number, 
                    );
                D("Page")->where(" page_id = '$page_id' ")->save($update_data);
            }
        }

        return $page_id ;
    }

   //软删除页面
   public function softDeletePage($page_id){
    $page_id = intval($page_id) ;
      //放入回收站
      $login_user = session('login_user');
      $page = D("Page")->field("item_id,page_title")->where(" page_id = '$page_id' ")->find() ;
      D("Recycle")->add(array(
        "item_id" =>$page['item_id'],
        "page_id" =>$page_id,
        "page_title" =>$page['page_title'],
        "del_by_uid" =>$login_user['uid'],
        "del_by_username" =>$login_user['username'],
        "del_time" =>time()
        ));
      $ret = M("Page")->where(" page_id = '$page_id' ")->save(array("is_del"=>1 ,"addtime"=>time()));
      return $ret;
   }

   //删除页面
   public function deletePage($page_id){
    $page_id = intval($page_id) ;
      $ret = M("Page")->where(" page_id = '$page_id' ")->delete();
      return $ret;
   }

   public function deleteFile($file_id){
    $file_id = intval($file_id) ;
        return D("Attachment")->deleteFile($file_id) ;
    }

    //把runapi的格式内容转换为markdown格式。如果不是runapi格式，则会返回false
    //参数content为json字符串或者数组
    public function runapiToMd($content){
        if(!is_array($content) ){
          $content_json = htmlspecialchars_decode($content) ;
          $content = json_decode($content_json , true) ;
        }
        if(!$content || !$content['info'] || !$content['info']['url'] ){
            return false ;
        }

        // 兼容query
        if($content['info']['method'] == 'get'){
            if(!$content['request']['query']){
                $content['request']['query'] = $content['request']['params'][$content['request']['params']['mode']];
            }
            $content['request']['params'][$content['request']['params']['mode']] = array();
        }

        $new_content = "
##### 简要描述
  
- ".($content['info']['description'] ? $content['info']['description'] :'无');


if($content['info']['apiStatus']){
    $statusText = '';
    switch ($content['info']['apiStatus']) {
        case '1':
            $statusText = '开发中';
            break;
        case '2':
        $statusText = '测试中';
        break;
        case '3':
        $statusText = '已完成';
        break;
        case '4':
        $statusText = '需修改';
        break;
        case '5':
        $statusText = '已废弃';
        break;
        default:
        break;
   }
 
   $new_content .= "
 
##### 接口状态
  - ".$statusText ;
 
 }

    // 如果有query参数组，则把url中的参数去掉
    $query = $content['request']['query'] ;
    if ($query && is_array($query) && $query[0] && $query[0]['name']){
        $words = explode('?',$content['info']['url']);
        $content['info']['url']  = $words[0] ;
    }

$new_content .= "
  
##### 请求URL
  
- `{$content['info']['url']}`
  
##### 请求方式
  
- {$content['info']['method']}
  ";

  $pathVariable = $content['request']['pathVariable'] ;
  if ($pathVariable && is_array($pathVariable) && $pathVariable[0] && $pathVariable[0]['name']){
    $new_content .= " 
##### 路径变量

|变量名|必选|类型|说明|
|:-----  |:-----|-----|
";

  foreach ($pathVariable as $key => $value) {
    $value['require'] = $value['require'] > 0 ? "是" : "否" ;
    $value['remark'] = $value['remark'] ? $value['remark'] : '无' ;
    $new_content .= "|{$value['name']}|  {$value['require']} |  {$value['type']} |  {$value['remark']} | \n";
  }
}

  
    if($content['request']['headers'] && $content['request']['headers'][0] && $content['request']['headers'][0]['name']){
        $new_content .= " 
##### Header 
  
|header|必选|类型|说明|
|:-----  |:-----|-----|
  ";
        foreach ($content['request']['headers'] as $key => $value) {
            $value['require'] = $value['require'] > 0 ? "是" : "否" ;
            $value['remark'] = $value['remark'] ? $value['remark'] : '无' ;
            $new_content .= "|{$value['name']}|  {$value['require']} |  {$value['type']} |  {$value['remark']} | \n";
        } 
    }
  
    $query = $content['request']['query'] ;
        if ($query && is_array($query) && $query[0] && $query[0]['name']){
        $new_content .= " 
##### 请求Query参数

|参数名|必选|类型|说明|
|:-----  |:-----|-----|
";

        foreach ($query as $key => $value) {
            $value['require'] = $value['require'] > 0 ? "是" : "否" ;
            $value['remark'] = $value['remark'] ? $value['remark'] : '无' ;
            $new_content .= "|{$value['name']}|  {$value['require']} |  {$value['type']} |  {$value['remark']} | \n";
        }
    }
  

    $params = $content['request']['params'][$content['request']['params']['mode']];
    if ($params && is_array($params) && $params[0] && $params[0]['name']){
        $new_content .= " 
##### 请求Body参数
  
|参数名|必选|类型|说明|
|:-----  |:-----|-----|
  ";
  
    foreach ($params as $key => $value) {
        $value['require'] = $value['require'] > 0 ? "是" : "否" ;
        $value['remark'] = $value['remark'] ? $value['remark'] : '无' ;
        $new_content .= "|{$value['name']}|  {$value['require']} |  {$value['type']} |  {$value['remark']} | \n";
    }
    }
    //如果参数类型为json
    if($content['request']['params']['mode'] == 'json' && $params){
        $params = $this->_indent_json($params);
        $new_content .= " 
##### 请求参数示例  
```
{$params}
  
``` 
  "; 
    }
        // json字段说明
        $jsonDesc = $content['request']['params']['jsonDesc'] ;
        if ($content['request']['params']['mode'] == 'json' && $jsonDesc && $jsonDesc[0] && $jsonDesc[0]['name']){
            $new_content .= " 
##### json字段说明
  
|字段名|必选|类型|说明|
|:-----  |:-----|-----|
  ";
    
        foreach ($jsonDesc as $key => $value) {
            $value['require'] = $value['require'] > 0 ? "是" : "否" ;
            $value['remark'] = $value['remark'] ? $value['remark'] : '无' ;
            $new_content .= "|{$value['name']}|  {$value['require']} |  {$value['type']} |  {$value['remark']} | \n";
        }
        }
  
        //返回示例
        if($content['response']['responseExample']){
          $responseExample = $this->_indent_json($content['response']['responseExample']);
          $responseExample = $responseExample ? $responseExample : $content['response']['responseExample'] ;
            $new_content .= " 
##### 成功返回示例  
```
{$responseExample}
  
``` 
  "; 
        }
  
        //返回示例说明
        if($content['response']['responseParamsDesc'] && $content['response']['responseParamsDesc'][0] && $content['response']['responseParamsDesc'][0]['name']){
            $new_content .= " 
##### 成功返回示例的参数说明 
  
|参数名|类型|说明|
|:-----  |:-----|-----|
  ";
            foreach ($content['response']['responseParamsDesc'] as $key => $value) {
                $value['remark'] = $value['remark'] ? $value['remark'] : '无' ;
                $new_content .= "|{$value['name']}| {$value['type']} |  {$value['remark']} | \n";
            }
        }

        //返回示例
        if($content['response']['responseFailExample']){
            $responseFailExample = $this->_indent_json($content['response']['responseFailExample']);
            $responseFailExample = $responseFailExample ? $responseFailExample : $content['response']['responseFailExample'] ;
              $new_content .= " 
  ##### 失败返回示例  
  ```
  {$responseFailExample}
    
  ``` 
    "; 
          }
    
          //返回示例说明
          if($content['response']['responseFailParamsDesc'] && $content['response']['responseFailParamsDesc'][0] && $content['response']['responseFailParamsDesc'][0]['name']){
              $new_content .= " 
  ##### 失败返回示例的参数说明 
    
  |参数名|类型|说明|
  |:-----  |:-----|-----|
    ";
              foreach ($content['response']['responseFailParamsDesc'] as $key => $value) {
                  $value['remark'] = $value['remark'] ? $value['remark'] : '无' ;
                  $new_content .= "|{$value['name']}| {$value['type']} |  {$value['remark']} | \n";
              }
          }
  
        $new_content .= " 
##### 备注
  
{$content['info']['remark']}
  ";
  
    
  
        return $new_content ;
  
    }
  
    // json美化
    private function _indent_json($json) {

      $json_new = json_encode(json_decode($json), JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
      if($json_new){
          return $json_new ;
      }
      return $json ;

    }

    private function _htmlspecialchars($str){
      if (!$str) {
          return '' ;
      }
      //之所以先htmlspecialchars_decode是为了防止被htmlspecialchars转义了两次
      return htmlspecialchars(htmlspecialchars_decode($str));
  }

}