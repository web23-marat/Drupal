<?php
namespace Drupal\uc_interkassa;
use GuzzleHttp\Exception\TransferException;

trait InterkassaPaymentTrait {
  public function createSign($data, $secret_key) {
    if (!empty($data['ik_sign'])) unset($data['ik_sign']);

    $dataSet = array();
    foreach ($data as $key => $value) {
      if (!preg_match('/ik_/', $key)) continue;
      $dataSet[$key] = $value;
    }

    ksort($dataSet, SORT_STRING);
    array_push($dataSet, $secret_key);
    $signString = implode(':', $dataSet);
    $sign = base64_encode(md5($signString, true));
    return $sign;
  }
  public function getAccountApi($configuration) {
    $accountId = "";
    $username = $configuration['api_id'];
    $password = $configuration['api_key'];
    if ($configuration['api_mode']) {
       $tmpLocationFile = __DIR__ . '/tmpLocalStorageBusinessAcc.ini';
            $dataBusinessAcc = function_exists('file_get_contents') ? file_get_contents($tmpLocationFile) : '{}';
            $dataBusinessAcc = json_decode($dataBusinessAcc, 1);
            $businessAcc = is_string($dataBusinessAcc['businessAcc']) ? trim($dataBusinessAcc['businessAcc']) : '';
            if (empty($businessAcc) || sha1($username . $password) !== $dataBusinessAcc['hash']) {
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, 'https://api.interkassa.com/v1/' . 'account');
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
                curl_setopt($curl, CURLOPT_HEADER, false);
                curl_setopt($curl, CURLOPT_HTTPHEADER, ["Authorization: Basic " . base64_encode("$username:$password")]);
                $response = curl_exec($curl);
                $response = json_decode($response,1);


                if (!empty($response['data'])) {
                    foreach ($response['data'] as $id => $data) {
                        if ($data['tp'] == 'b') {
                            $businessAcc = $id;
                            break;
                        }
                    }
                }

                if (function_exists('file_put_contents')) {
                    $updData = [
                        'businessAcc' => $businessAcc,
                        'hash' => sha1($username . $password)
                    ];
                    file_put_contents($tmpLocationFile, json_encode($updData, JSON_PRETTY_PRINT));
                }

                return $businessAcc;
            }

            return $businessAcc;
    }
    return $businessAcc;
  }

  public function getPayments($data,$host) {
    $paywaySet = array();
      try {
        $response = \Drupal::httpClient()->request('POST', $host, [
          'form_params' => $data,
        ]);
      }
      catch (TransferException  $e) {
        \Drupal::logger('uc_interkassa')->error('Interkassa request failed with HTTP error %error.', ['%error' => $e->getMessage()]);
        \Drupal::messenger()->addWarning('Interkassa request failed with HTTP error.' . $e->getMessage());
        return array();
      }

      if(isset($response) && $response) {
        $returnInter = \GuzzleHttp\json_decode($response->getBody()->getContents());
        if ($returnInter->resultMsg == "Success" && $returnInter->resultCode == 0) {
          $paywaySet = $returnInter->resultData;

        }
      }
      if (!$paywaySet) {
        \Drupal::messenger()->addWarning('Something was wrong!');
      }
    return $paywaySet;
  }
  public function getPaymentsAPI($configuration) {
      $account_id = $this->getAccountApi($configuration); 
  $payment_systems = array();
    if ($configuration['api_mode']) {
      $host = $configuration['hostPaySystem']."?checkoutId=" . $configuration['sid'];
      try {
        $response = \Drupal::httpClient()->request('GET', $host, [
          'headers' => [
            'Authorization' => "Basic " . base64_encode($configuration['api_id'] . ":" . $configuration['api_key']), 'Ik-Api-Account-Id' => $account_id
          ],
        ]);

      } catch (TransferException  $e) {
        \Drupal::logger('uc_interkassa')
          ->error('API request failed with HTTP error %error.', ['%error' => $e->getMessage()]);
        \Drupal::messenger()
          ->addWarning('API request failed with HTTP error.' . $e->getMessage());
        \Drupal::messenger()->addWarning('Invalid API parameters');
        return $payment_systems;
      }
      if(isset($response) && $response) {
        $returnInter = json_decode($response->getBody()
          ->getContents());
        if ($returnInter->status == "ok" && $returnInter->code == 0) {
          $payways = $returnInter->data;
        }
      }
      if (!$payways) {
        \Drupal::messenger()->addWarning('Something was wrong!');
      }
      else {
        foreach ($payways as $ps => $info) {
          $payment_system = $info->ser;

          if (!array_key_exists($payment_system, $payment_systems)) {
            $payment_systems[$payment_system] = array();
            foreach ($info->name as $name) {
              if ($name->l == 'en') {
                $payment_systems[$payment_system]['title'] = ucfirst($name->v);
              }
              $payment_systems[$payment_system]['name'][$name->l] = $name->v;
            }
          }
          $payment_systems[$payment_system]['currency'][strtoupper($info->curAls)] = $info->als;
        }
      }


      return $payment_systems;
    }
  }
  public function checkIP() {
    $ip_stack = array(
      'ip_begin' => '151.80.190.97',
      'ip_end' => '151.80.190.104'
    );

    if (!ip2long($_SERVER['REMOTE_ADDR']) >= ip2long($ip_stack['ip_begin']) && !ip2long($_SERVER['REMOTE_ADDR']) <= ip2long($ip_stack['ip_end'])) {
      \Drupal::messenger()->addWarning('REQUEST IP' . $_SERVER['REMOTE_ADDR'] . 'doesnt match');
      return FALSE;
    }
    return TRUE;
  }

}