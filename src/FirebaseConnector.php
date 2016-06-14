<?php

namespace FirebaseConnector;

use \Exception;

class FirebaseConnector
{
    public function __invoke($payload=[])
    {
      if (isset($payload["response"])) {
        return $payload;
      }
        $configs=isset($payload["configs"]) ? $payload["configs"] : [];
        $baseConfig=isset($payload["connectorBaseConfig"]) ? $payload["connectorBaseConfig"] : [];

        if(empty($baseConfig["databaseURL"])||empty($configs["path"])){
            throw new Exception('Insufficient configuration: unable to resolve to a data path');
        }
        if (!empty($baseConfig["apiKey"])) {
          $firebase = new \Firebase\FirebaseLib($baseConfig["databaseURL"], $baseConfig["apiKey"]);
        } else {
          $firebase = new \Firebase\FirebaseLib($baseConfig["databaseURL"]);
        }

        return $payload["isMutation"] ? $this->execute($payload,$firebase) : $this->resolve($payload,$firebase);

    }

    public function resolve($payload=[],$firebase){
        $multivalued=isset($payload["multivalued"]) ? $payload["multivalued"] : false;
        $args=isset($payload["args"]) ? $payload["args"] : [];
        $basePath=$payload["configs"]["path"];
        $path=$basePath;

        $argsList = [];
        $needOrderBy = false;
        $sort=null;
        if(!empty($payload["pipelineParams"]["orderBy"])){
            $direction=!empty($payload["pipelineParams"]["orderByDirection"])&&($payload["pipelineParams"]["orderByDirection"]==-"desc") ? -1 : 1;
            $sort=[$payload["pipelineParams"]["orderBy"]=>$direction];
        }
        $start=!empty($payload["pipelineParams"]["start"]) ? $payload["pipelineParams"]["start"] : null;
        $limit=!empty($payload["pipelineParams"]["limit"]) ? $payload["pipelineParams"]["limit"] : null;
        if (isset($limit)) {
          $argsList["limitToFirst"]=$limit;
          $needOrderBy = true;
        }
        if (isset($start)) {
          $argsList["startAt"]=$start;
          $needOrderBy = true;
        }

        foreach($args as $argKey=>$argValue){
            switch ($argKey) {
              case "limitToLast":
                    $argsList["limitToLast"]=$argValue;
                    $needOrderBy = true;
                    break;
              default:
                    $argsList["orderBy"]=$argKey;
                    $argsList["equalTo"]=$argValue;
                  break;
            }
        }
        if ($needOrderBy && !isset($argsList["orderBy"])) {
            $argsList["orderBy"] = '$key';
        }
        try {
          $data = $firebase->get($basePath, $argsList);
          $result=json_decode($data,true);
        } catch (Exception $exception) {
            throw new Exception($exception->getMessage());
        }
        if (!isset($result["error"])) {
          if (!$multivalued) {
            return $result[0];
          } else {
            $resultList = [];
            foreach ($result as $value) {
              $resultList[] = $value;
            }
            $payload["response"] = $resultList;
            return $payload;
          }
        } else {
          throw new Exception("Firebase error: ".$result["error"]);
        }

    }

    public function execute($payload=[],$jsonArray,$filePath){
        $args=isset($payload["args"]) ? $payload["args"] : [];
        $methodName=isset($payload["methodName"]) ? $payload["methodName"] : null;
        if(empty($methodName)){
            throw new Exception('Firebase connector requires a valid methodName for write ops');
        }
        if(empty($args["id"])){
            throw new Exception('Firebase connector id for write ops');
        }
        $result=[];
        // TODO
        return $result;
    }

}
