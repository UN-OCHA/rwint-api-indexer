<?php

declare(strict_types=1);

namespace RWAPIIndexer\Tests;

use RWAPIIndexer\Elasticsearch;

/**
 * Testable Elasticsearch handler exposing transport helpers.
 */
class TestableElasticsearch extends Elasticsearch {

  /**
   * Build auth headers for the request.
   *
   * @return array<int, string>
   *   Authentication headers.
   */
  public function buildAuthHeaders(): array {
    $headers = [];
    $this->applyAuthenticationHeaders($headers);
    return $headers;
  }

  /**
   * Build request headers without sending a request.
   *
   * @param mixed $data
   *   Optional request payload.
   * @param bool $bulk
   *   Whether this is a bulk request.
   *
   * @return array<int, string>
   *   Request headers.
   */
  public function buildHeaders(mixed $data = NULL, bool $bulk = FALSE): array {
    return $this->buildRequestHeaders($data, $bulk);
  }

  /**
   * Get TLS cURL options for the request.
   *
   * @return array<int, mixed>
   *   cURL SSL options.
   */
  public function tlsOptions(): array {
    return $this->tlsCurlOptions();
  }

  /**
   * Override authentication settings for tests.
   *
   * @param string $auth_type
   *   Authentication type.
   * @param string $username
   *   Basic authentication username.
   * @param string $password
   *   Basic authentication password.
   * @param string $bearer_token
   *   Bearer authentication token.
   */
  public function setAuth(
    string $auth_type,
    string $username = '',
    string $password = '',
    string $bearer_token = '',
  ): void {
    $this->authType = $auth_type;
    $this->username = $username;
    $this->password = $password;
    $this->bearerToken = $bearer_token;
  }

}
