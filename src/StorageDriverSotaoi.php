<?php namespace Sotaoi;

use CURLFile;

$output = '';

class StorageCommandResult {
  const APP_GENERIC_ERROR = 'app.generic.error';

  public $code;
  public $errorCode;
  public $success;
  public $title;
  public $msg;
  public $xdata;
  public $validations;

  public function __construct(\stdClass $result) {
    $this->success = isset($result->success) &&
      $result->success === true &&
      isset($result->code) &&
      is_int($result->code) &&
      $result->code >= 200 &&
      $result->code < 300 &&
      (!isset($result->errorCode) || (isset($result->errorCode) && !$result->errorCode)) &&
      isset($result->success) &&
      $result->success
      ? true
      : false;
    $this->code = StorageDriverSotaoi::statusCode($result->code ?? 400, $this->success);
    $this->errorCode = $this->success ? null : ($result->errorCode ?? static::APP_GENERIC_ERROR);
    $this->title = $result->title ?? ($this->success ? 'Success' : 'Error');
    $this->msg = $result->msg ?? ($this->success ? 'Everything looks good' : 'Something went wrong');
    $this->xdata = $result->xdata ?? (object) [];
    $this->validations = $result->validations ?? null;
  }
}

class StorageDriverSotaoi {
  const SUBMIT_TIMEOUT_IN_MS = 5000;

  protected $storageUrl = null;
  protected $clientName = null;
  protected $clientId = null;
  protected $clientSecret = null;
  protected $clientKey = null;

  public function __construct(?string $storageUrl, ?string $clientName, ?string $clientId, ?string $clientSecret, ?string $clientKey, bool $preferSecure = true)
  {
    if ($storageUrl) {
      while (mb_substr($storageUrl, mb_strlen($storageUrl) - 2, 1) === '/') {
        $storageUrl = mb_substr($storageUrl, 0, mb_strlen($storageUrl) - 2);
      }
      if (mb_substr($storageUrl, 0, 7) !== 'http://' && mb_substr($storageUrl, 0, 8) !=='https://') {
        $storageUrl = $preferSecure ? ('https://' . $storageUrl) : ('http://' . $storageUrl);
      }
    }
    $this->storageUrl = $storageUrl;
    $this->clientName = $clientName ? $clientName : null;
    $this->clientId = $clientId ? $clientId : null;
    $this->clientSecret = $clientSecret ? $clientSecret : null;
    $this->clientKey = $clientKey ? $clientKey : null;
  }

  public function alink(?string $filepath): ?string {
    return $this->assetLink($filepath);
  }

  public function assetLink(?string $filepath): ?string {
    if (!$this->storageUrl || !$this->clientKey || !$filepath)  {
      return null;
    }
    while (mb_substr($filepath, 0, 1) === '/') {
      $filepath = mb_substr($filepath, 1, mb_strlen($filepath) - 1);
    }
    return $this->storageUrl . '/asset/' . $this->clientKey . '/' . $filepath;
  }

  public function storeAsset(string $filepath, string $asset): StorageCommandResult {
    try {
      $filename = sha1($filepath . ':' . mb_substr($asset, 0, 256)) . '.tmp';
      $tmpFile = __DIR__ . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . $filename;
      file_put_contents($tmpFile, $asset);
      $file = new CURLFile($tmpFile, mime_content_type($tmpFile), $filename);

      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $this->storageUrl . '/asset/store');
      curl_setopt($ch, CURLOPT_POST, true);
      // curl_setopt($ch, CURLOPT_HTTPHEADER, []);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'clientName' => $this->clientName,
        'clientId' => $this->clientId,
        'clientSecret' => $this->clientSecret,
        'filepath' => $filepath,
        'asset' => $file,
      ]);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_TIMEOUT_MS, static::SUBMIT_TIMEOUT_IN_MS);
      $response = curl_exec($ch);
      curl_close($ch);
      unlink($tmpFile);

      $result = json_decode($response);
      $success = !!(
        isset($result->success) &&
          $result->success === true &&
          isset($result->code) &&
          is_int($result->code) &&
          $result->code < 300 &&
          $result->code >= 200
      );
      return new StorageCommandResult((object) [
        'code' => static::statusCode($result->code ?? 400, $success),
        'success' => $success,
        'title' => $success ? ($result->title ?? 'Success') : ($result->title ?? 'Error'),
        'msg' => $success ? ($result->msg ?? 'Asset storing successful') : ($result->msg ?? 'Asset storing failed'),
        'validations' => null,
        'xdata' => (object) [],
      ]);
    } catch (\Exception $ex) {
      $code = $ex->getCode() ? $ex->getCode() : 400;
      return new StorageCommandResult((object) [
        'code' => $code,
        'success' => false,
        'title' => 'Error',
        'msg' => $ex->getMessage() ? $ex->getMessage() : 'Asset storing failed',
        'validations' => null,
        'xdata' => (object) [],
      ]);
    }
  }

  public function removeAsset(string $filepath): StorageCommandResult {
    try {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $this->storageUrl . '/asset/remove');
      curl_setopt($ch, CURLOPT_POST, true);
      // curl_setopt($ch, CURLOPT_HTTPHEADER, []);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'clientName' => $this->clientName,
        'clientId' => $this->clientId,
        'clientSecret' => $this->clientSecret,
        'filepath' => $filepath,
      ]);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_TIMEOUT_MS, static::SUBMIT_TIMEOUT_IN_MS);
      $response = curl_exec($ch);
      curl_close($ch);

      $result = json_decode($response);
      $success = !!(
        isset($result->success) &&
          $result->success === true &&
          isset($result->code) &&
          is_int($result->code) &&
          $result->code < 300 &&
          $result->code >= 200
      );
      return new StorageCommandResult((object) [
        'code' => static::statusCode($result->code ?? 400, $success),
        'success' => $success,
        'title' => $success ? ($result->title ?? 'Success') : ($result->title ?? 'Error'),
        'msg' => $success ? ($result->msg ?? 'Asset removed successfully') : ($result->msg ?? 'Asset removal failed'),
        'validations' => null,
        'xdata' => (object) [],
      ]);
    } catch (\Exception $ex) {
      $code = $ex->getCode() ? $ex->getCode() : 400;
      return new StorageCommandResult((object) [
        'code' => $code,
        'success' => false,
        'title' => 'Error',
        'msg' => $ex->getMessage() ? $ex->getMessage() : 'Asset removal failed',
        'validations' => null,
        'xdata' => (object) [],
      ]);
    }
  }

  public function checkAssetUrl(string $url): StorageCommandResult {
    try {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $this->storageUrl . '/asset/check-url');
      curl_setopt($ch, CURLOPT_POST, true);
      // curl_setopt($ch, CURLOPT_HTTPHEADER, []);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'clientName' => $this->clientName,
        'clientId' => $this->clientId,
        'clientSecret' => $this->clientSecret,
        'clientKey' => $this->clientKey,
        'url' => $url,
      ]);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_TIMEOUT_MS, static::SUBMIT_TIMEOUT_IN_MS);
      $response = curl_exec($ch);
      curl_close($ch);

      return new StorageCommandResult(json_decode($response));
    } catch (\Exception $ex) {
      $code = $ex->getCode() ? $ex->getCode() : 400;
      return new StorageCommandResult((object) [
        'code' => $code,
        'success' => false,
        'title' => 'Error',
        'msg' => $ex->getMessage() ? $ex->getMessage() : 'Asset url check failed',
        'validations' => null,
        'xdata' => (object) [],
      ]);
    }
  }

  public function checkAssetFilepath(string $filepath): StorageCommandResult {
    try {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $this->storageUrl . '/asset/check-filepath');
      curl_setopt($ch, CURLOPT_POST, true);
      // curl_setopt($ch, CURLOPT_HTTPHEADER, []);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'clientName' => $this->clientName,
        'clientId' => $this->clientId,
        'clientSecret' => $this->clientSecret,
        'clientKey' => $this->clientKey,
        'filepath' => $filepath,
      ]);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_TIMEOUT_MS, static::SUBMIT_TIMEOUT_IN_MS);
      $response = curl_exec($ch);
      curl_close($ch);

      return new StorageCommandResult(json_decode($response));
    } catch (\Exception $ex) {
      $code = $ex->getCode() ? $ex->getCode() : 400;
      return new StorageCommandResult((object) [
        'code' => $code,
        'success' => false,
        'title' => 'Error',
        'msg' => $ex->getMessage() ? $ex->getMessage() : 'Asset filepath check failed',
        'validations' => null,
        'xdata' => (object) [],
      ]);
    }
  }

  public function storeDoc(string $docpath, string $doc): StorageCommandResult {
    try {
      $filename = sha1($docpath . ':' . mb_substr($doc, 0, 256)) . '.tmp';
      $tmpFile = __DIR__ . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . $filename;
      file_put_contents($tmpFile, $doc);
      $file = new CURLFile($tmpFile, mime_content_type($tmpFile), $filename);

      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $this->storageUrl . '/doc/store');
      curl_setopt($ch, CURLOPT_POST, true);
      // curl_setopt($ch, CURLOPT_HTTPHEADER, []);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'clientName' => $this->clientName,
        'clientId' => $this->clientId,
        'clientSecret' => $this->clientSecret,
        'docpath' => $docpath,
        'doc' => $file,
      ]);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_TIMEOUT_MS, static::SUBMIT_TIMEOUT_IN_MS);
      $response = curl_exec($ch);
      curl_close($ch);
      unlink($tmpFile);

      $result = json_decode($response);
      $success = !!(
        isset($result->success) &&
          $result->success === true &&
          isset($result->code) &&
          is_int($result->code) &&
          $result->code < 300 &&
          $result->code >= 200
      );
      return new StorageCommandResult((object) [
        'code' => static::statusCode($result->code ?? 400, $success),
        'success' => $success,
        'title' => $success ? ($result->title ?? 'Success') : ($result->title ?? 'Error'),
        'msg' => $success ? ($result->msg ?? 'Document storing successful') : ($result->msg ?? 'Document storing failed'),
        'validations' => null,
        'xdata' => (object) [],
      ]);
    } catch (\Exception $ex) {
      $code = $ex->getCode() ? $ex->getCode() : 400;
      return new StorageCommandResult((object) [
        'code' => $code,
        'success' => false,
        'title' => 'Error',
        'msg' => $ex->getMessage() ? $ex->getMessage() : 'Document storing failed',
        'validations' => null,
        'xdata' => (object) [],
      ]);
    }
  }

  public function retrieveDoc(string $docpath): string {
    $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $this->storageUrl . '/doc/retrieve');
      curl_setopt($ch, CURLOPT_POST, true);
      // curl_setopt($ch, CURLOPT_HTTPHEADER, []);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'clientName' => $this->clientName,
        'clientId' => $this->clientId,
        'clientSecret' => $this->clientSecret,
        'docpath' => $docpath,
      ]);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_TIMEOUT_MS, static::SUBMIT_TIMEOUT_IN_MS);
      $response = curl_exec($ch);
      curl_close($ch);

      return $response;
  }

  public function removeDoc(string $docpath): StorageCommandResult {
    try {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $this->storageUrl . '/doc/remove');
      curl_setopt($ch, CURLOPT_POST, true);
      // curl_setopt($ch, CURLOPT_HTTPHEADER, []);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'clientName' => $this->clientName,
        'clientId' => $this->clientId,
        'clientSecret' => $this->clientSecret,
        'docpath' => $docpath,
      ]);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_TIMEOUT_MS, static::SUBMIT_TIMEOUT_IN_MS);
      $response = curl_exec($ch);
      curl_close($ch);

      $result = json_decode($response);
      $success = !!(
        isset($result->success) &&
          $result->success === true &&
          isset($result->code) &&
          is_int($result->code) &&
          $result->code < 300 &&
          $result->code >= 200
      );
      return new StorageCommandResult((object) [
        'code' => static::statusCode($result->code ?? 400, $success),
        'success' => $success,
        'title' => $success ? ($result->title ?? 'Document Removed') : ($result->title ?? 'Error'),
        'msg' => $success ? ($result->msg ?? 'Document removal successful') : ($result->msg ?? 'Document removal failed'),
        'validations' => null,
        'xdata' => (object) [],
      ]);
    } catch (\Exception $ex) {
      $code = $ex->getCode() ? $ex->getCode() : 400;
      return new StorageCommandResult((object) [
        'code' => $code,
        'success' => false,
        'title' => 'Error',
        'msg' => $ex->getMessage() ? $ex->getMessage() : 'Document removal failed',
        'validations' => null,
        'xdata' => (object) [],
      ]);
    }
  }

  public function checkDocpath(string $docpath): StorageCommandResult {
    try {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $this->storageUrl . '/doc/check-docpath');
      curl_setopt($ch, CURLOPT_POST, true);
      // curl_setopt($ch, CURLOPT_HTTPHEADER, []);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'clientName' => $this->clientName,
        'clientId' => $this->clientId,
        'clientSecret' => $this->clientSecret,
        'docpath' => $docpath,
      ]);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_TIMEOUT_MS, static::SUBMIT_TIMEOUT_IN_MS);
      $response = curl_exec($ch);
      curl_close($ch);

      $result = json_decode($response);
      $success = !!(
        isset($result->success) &&
          $result->success === true &&
          isset($result->code) &&
          is_int($result->code) &&
          $result->code < 300 &&
          $result->code >= 200
      );
      return new StorageCommandResult((object) [
        'code' => static::statusCode($result->code ?? 400, $success),
        'success' => $success,
        'title' => $success ? ($result->title ?? 'Document Checked') : ($result->title ?? 'Error'),
        'msg' => $success ? ($result->msg ?? 'Document check successful') : ($result->msg ?? 'Document check failed'),
        'validations' => null,
        'xdata' => (object) [],
      ]);
    } catch (\Exception $ex) {
      $code = $ex->getCode() ? $ex->getCode() : 400;
      return new StorageCommandResult((object) [
        'code' => $code,
        'success' => false,
        'title' => 'Error',
        'msg' => $ex->getMessage() ? $ex->getMessage() : 'Document check failed',
        'validations' => null,
        'xdata' => (object) [],
      ]);
    }
  }

  public static function statusCode($statusCode, bool $success): int {
    if (!isset($statusCode) || !is_int($statusCode)) {
      return $success ? 200 : 400;
    }
    if ($success) {
      return $statusCode >= 200 && $statusCode < 300 ? $statusCode : 200;
    }
    if (!$success) {
      return $statusCode < 200 && $statusCode >= 300 ? $statusCode : 400;
    }
  }
}
