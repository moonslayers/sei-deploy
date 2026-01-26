# Ejemplos de Implementación de Clientes

Este archivo contiene ejemplos de código para diferentes lenguajes y frameworks que necesitan interactuar con las APIs de SEI Deploy.

## Requisitos Comunes

Antes de implementar, necesitas:
1. La llave privada RSA (obtenida del administrador del servidor)
2. La URL del servidor de despliegue
3. El nombre del repositorio y branch según corresponda

## PHP

### Cliente Simple (usando cURL)

```php
<?php

class SeiDeployClient
{
    private string $serverUrl;
    private string $privateKey;

    public function __construct(string $serverUrl, string $privateKeyPath)
    {
        $this->serverUrl = rtrim($serverUrl, '/');
        $this->privateKey = file_get_contents($privateKeyPath);
    }

    private function signPayload(array $data): string
    {
        $payload = json_encode($data, JSON_UNESCAPED_SLASHES);
        openssl_sign($payload, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }

    public function updateSubmodule(string $repository = 'sei-compilados', string $branch = 'main', ?string $project = null): array
    {
        $data = [
            'repository' => $repository,
            'branch' => $branch,
            'timestamp' => time(),
        ];

        if ($project !== null) {
            $data['project'] = $project;
        }

        $payload = json_encode($data, JSON_UNESCAPED_SLASHES);
        $signature = $this->signPayload($data);

        $ch = curl_init($this->serverUrl . '/api/webhook/submodule');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Webhook-Signature: ' . $signature,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $statusCode,
            'body' => json_decode($response, true),
        ];
    }

    public function deploy(string $branch = 'main'): array
    {
        $data = [
            'branch' => $branch,
            'timestamp' => time(),
        ];

        $payload = json_encode($data, JSON_UNESCAPED_SLASHES);
        $signature = $this->signPayload($data);

        $ch = curl_init($this->serverUrl . '/api/webhook/deploy');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Webhook-Signature: ' . $signature,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $statusCode,
            'body' => json_decode($response, true),
        ];
    }

    public function getStatus(): array
    {
        $ch = curl_init($this->serverUrl . '/api/status');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $statusCode,
            'body' => json_decode($response, true),
        ];
    }
}

// Uso
$client = new SeiDeployClient('http://tu-servicio.com', '/path/to/private_key.pem');

// Actualizar submódulo
$result = $client->updateSubmodule();
print_r($result);

// Desplegar
$result = $client->deploy();
print_r($result);

// Verificar estado
$result = $client->getStatus();
print_r($result);
```

### Cliente con Laravel

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SeiDeployService
{
    private string $serverUrl;
    private string $privateKey;

    public function __construct()
    {
        $this->serverUrl = config('sei-deploy.server_url');
        $this->privateKey = file_get_contents(config('sei-deploy.private_key_path'));
    }

    private function signPayload(array $data): string
    {
        $payload = json_encode($data, JSON_UNESCAPED_SLASHES);
        openssl_sign($payload, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }

    public function updateSubmodule(string $repository = 'sei-compilados', string $branch = 'main', ?string $project = null): array
    {
        $data = [
            'repository' => $repository,
            'branch' => $branch,
            'timestamp' => time(),
        ];

        if ($project !== null) {
            $data['project'] = $project;
        }

        $payload = json_encode($data, JSON_UNESCAPED_SLASHES);
        $signature = $this->signPayload($data);

        $response = Http::withHeaders([
            'Content-Type: application/json',
            'X-Webhook-Signature' => $signature,
        ])->post($this->serverUrl . '/api/webhook/submodule', $data);

        return [
            'status' => $response->status(),
            'body' => $response->json(),
        ];
    }

    public function deploy(string $branch = 'main'): array
    {
        $data = [
            'branch' => $branch,
            'timestamp' => time(),
        ];

        $payload = json_encode($data, JSON_UNESCAPED_SLASHES);
        $signature = $this->signPayload($data);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-Webhook-Signature' => $signature,
        ])->post($this->serverUrl . '/api/webhook/deploy', $data);

        return [
            'status' => $response->status(),
            'body' => $response->json(),
        ];
    }

    public function getStatus(): array
    {
        $response = Http::get($this->serverUrl . '/api/status');

        return [
            'status' => $response->status(),
            'body' => $response->json(),
        ];
    }
}

// Config (config/sei-deploy.php)
return [
    'server_url' => env('SEI_DEPLOY_SERVER_URL'),
    'private_key_path' => env('SEI_DEPLOY_PRIVATE_KEY_PATH'),
];
```

## Python

### Cliente con `requests` y `cryptography`

```python
import requests
import json
import time
import base64
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import padding

class SeiDeployClient:
    def __init__(self, server_url, private_key_path):
        self.server_url = server_url.rstrip('/')
        self.private_key = self._load_private_key(private_key_path)

    def _load_private_key(self, path):
        with open(path, "rb") as f:
            return serialization.load_pem_private_key(
                f.read(),
                password=None
            )

    def _sign_payload(self, data):
        payload = json.dumps(data, separators=(',', ':'))
        signature = self.private_key.sign(
            payload.encode(),
            padding.PKCS1v15(),
            hashes.SHA256()
        )
        return base64.b64encode(signature).decode()

    def update_submodule(self, repository='sei-compilados', branch='main', project=None):
        data = {
            'repository': repository,
            'branch': branch,
            'timestamp': int(time.time())
        }

        if project is not None:
            data['project'] = project

        payload = json.dumps(data, separators=(',', ':'))
        signature = self._sign_payload(data)

        response = requests.post(
            f'{self.server_url}/api/webhook/submodule',
            data=payload,
            headers={
                'Content-Type': 'application/json',
                'X-Webhook-Signature': signature
            }
        )

        return {
            'status': response.status_code,
            'body': response.json()
        }

    def deploy(self, branch='main'):
        data = {
            'branch': branch,
            'timestamp': int(time.time())
        }

        payload = json.dumps(data, separators=(',', ':'))
        signature = self._sign_payload(data)

        response = requests.post(
            f'{self.server_url}/api/webhook/deploy',
            data=payload,
            headers={
                'Content-Type': 'application/json',
                'X-Webhook-Signature': signature
            }
        )

        return {
            'status': response.status_code,
            'body': response.json()
        }

    def get_status(self):
        response = requests.get(f'{self.server_url}/api/status')
        return {
            'status': response.status_code,
            'body': response.json()
        }

# Uso
client = SeiDeployClient('http://tu-servicio.com', '/path/to/private_key.pem')

# Actualizar submódulo
result = client.update_submodule()
print(result)

# Desplegar
result = client.deploy()
print(result)

# Verificar estado
result = client.get_status()
print(result)
```

### Cliente Simple con `pycryptodome`

```python
import requests
import json
import time
import base64
from Crypto.PublicKey import RSA
from Crypto.Signature import pkcs1_15
from Crypto.Hash import SHA256

class SeiDeployClient:
    def __init__(self, server_url, private_key_path):
        self.server_url = server_url.rstrip('/')
        with open(private_key_path, 'r') as f:
            self.private_key = RSA.import_key(f.read())

    def _sign_payload(self, data):
        payload = json.dumps(data, separators=(',', ':'))
        h = SHA256.new(payload.encode())
        signature = pkcs1_15.new(self.private_key).sign(h)
        return base64.b64encode(signature).decode()

    def update_submodule(self, repository='sei-compilados', branch='main'):
        data = {
            'repository': repository,
            'branch': branch,
            'timestamp': int(time.time())
        }

        payload = json.dumps(data, separators=(',', ':'))
        signature = self._sign_payload(data)

        response = requests.post(
            f'{self.server_url}/api/webhook/submodule',
            data=payload,
            headers={
                'Content-Type': 'application/json',
                'X-Webhook-Signature': signature
            }
        )

        return {
            'status': response.status_code,
            'body': response.json()
        }

    # ... métodos deploy() y get_status() similares
```

## Node.js

### Cliente con módulo `crypto` nativo

```javascript
const crypto = require('crypto');
const fs = require('fs');
const https = require('https');
const http = require('http');

class SeiDeployClient {
    constructor(serverUrl, privateKeyPath) {
        this.serverUrl = serverUrl.replace(/\/$/, '');
        this.privateKey = fs.readFileSync(privateKeyPath);
    }

    signPayload(data) {
        const payload = JSON.stringify(data);
        const signature = crypto.sign(
            'sha256',
            Buffer.from(payload),
            {
                key: this.privateKey,
                padding: crypto.constants.RSA_PKCS1_PADDING
            }
        );
        return signature.toString('base64');
    }

    async makeRequest(endpoint, data) {
        const payload = JSON.stringify(data);
        const signature = this.signPayload(data);

        const options = {
            hostname: new URL(this.serverUrl).hostname,
            port: new URL(this.serverUrl).port || 80,
            path: endpoint,
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Webhook-Signature': signature,
                'Content-Length': Buffer.byteLength(payload)
            }
        };

        return new Promise((resolve, reject) => {
            const req = http.request(options, (res) => {
                let body = '';
                res.on('data', (chunk) => body += chunk);
                res.on('end', () => {
                    resolve({
                        status: res.statusCode,
                        body: JSON.parse(body)
                    });
                });
            });

            req.on('error', reject);
            req.write(payload);
            req.end();
        });
    }

    async updateSubmodule(repository = 'sei-compilados', branch = 'main', project = null) {
        const data = {
            repository,
            branch,
            timestamp: Math.floor(Date.now() / 1000)
        };

        if (project !== null) {
            data.project = project;
        }

        return this.makeRequest('/api/webhook/submodule', data);
    }

    async deploy(branch = 'main') {
        const data = {
            branch,
            timestamp: Math.floor(Date.now() / 1000)
        };

        return this.makeRequest('/api/webhook/deploy', data);
    }

    async getStatus() {
        return new Promise((resolve, reject) => {
            http.get(`${this.serverUrl}/api/status`, (res) => {
                let body = '';
                res.on('data', (chunk) => body += chunk);
                res.on('end', () => {
                    resolve({
                        status: res.statusCode,
                        body: JSON.parse(body)
                    });
                });
            }).on('error', reject);
        });
    }
}

// Uso
const client = new SeiDeployClient('http://tu-servicio.com', '/path/to/private_key.pem');

(async () => {
    // Actualizar submódulo
    let result = await client.updateSubmodule();
    console.log(result);

    // Desplegar
    result = await client.deploy();
    console.log(result);

    // Verificar estado
    result = await client.getStatus();
    console.log(result);
})();
```

### Cliente con `axios`

```javascript
const crypto = require('crypto');
const fs = require('fs');
const axios = require('axios');

class SeiDeployClient {
    constructor(serverUrl, privateKeyPath) {
        this.serverUrl = serverUrl.replace(/\/$/, '');
        this.privateKey = fs.readFileSync(privateKeyPath);
    }

    signPayload(data) {
        const payload = JSON.stringify(data);
        const signature = crypto.sign(
            'sha256',
            Buffer.from(payload),
            {
                key: this.privateKey,
                padding: crypto.constants.RSA_PKCS1_PADDING
            }
        );
        return signature.toString('base64');
    }

    async updateSubmodule(repository = 'sei-compilados', branch = 'main', project = null) {
        const data = {
            repository,
            branch,
            timestamp: Math.floor(Date.now() / 1000)
        };

        if (project !== null) {
            data.project = project;
        }

        const payload = JSON.stringify(data);
        const signature = this.signPayload(data);

        try {
            const response = await axios.post(
                `${this.serverUrl}/api/webhook/submodule`,
                payload,
                {
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Webhook-Signature': signature
                    }
                }
            );

            return {
                status: response.status,
                body: response.data
            };
        } catch (error) {
            return {
                status: error.response?.status || 500,
                body: error.response?.data || { error: error.message }
            };
        }
    }

    async deploy(branch = 'main') {
        const data = {
            branch,
            timestamp: Math.floor(Date.now() / 1000)
        };

        const payload = JSON.stringify(data);
        const signature = this.signPayload(data);

        try {
            const response = await axios.post(
                `${this.serverUrl}/api/webhook/deploy`,
                payload,
                {
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Webhook-Signature': signature
                    }
                }
            );

            return {
                status: response.status,
                body: response.data
            };
        } catch (error) {
            return {
                status: error.response?.status || 500,
                body: error.response?.data || { error: error.message }
            };
        }
    }

    async getStatus() {
        try {
            const response = await axios.get(`${this.serverUrl}/api/status`);
            return {
                status: response.status,
                body: response.data
            };
        } catch (error) {
            return {
                status: error.response?.status || 500,
                body: error.response?.data || { error: error.message }
            };
        }
    }
}

// Uso
const client = new SeiDeployClient('http://tu-servicio.com', '/path/to/private_key.pem');

(async () => {
    let result = await client.updateSubmodule();
    console.log(result);

    result = await client.deploy();
    console.log(result);

    result = await client.getStatus();
    console.log(result);
})();
```

## Go

### Cliente con biblioteca estándar

```go
package main

import (
	"bytes"
	"crypto"
	"crypto/rand"
	"crypto/rsa"
	"crypto/sha256"
	"crypto/x509"
	"encoding/base64"
	"encoding/json"
	"fmt"
	"io/ioutil"
	"net/http"
	"time"
)

type SeiDeployClient struct {
	ServerURL  string
	PrivateKey *rsa.PrivateKey
	Client     *http.Client
}

func NewSeiDeployClient(serverURL, privateKeyPath string) (*SeiDeployClient, error) {
	keyData, err := ioutil.ReadFile(privateKeyPath)
	if err != nil {
		return nil, err
	}

	privateKey, err := x509.ParsePKCS1PrivateKey(keyData)
	if err != nil {
		return nil, err
	}

	return &SeiDeployClient{
		ServerURL:  serverURL,
		PrivateKey: privateKey,
		Client:     &http.Client{Timeout: 30 * time.Second},
	}, nil
}

func (c *SeiDeployClient) signPayload(data interface{}) (string, error) {
	payload, err := json.Marshal(data)
	if err != nil {
		return "", err
	}

	hashed := sha256.Sum256(payload)
	signature, err := rsa.SignPKCS1v15(rand.Reader, c.PrivateKey, crypto.SHA256, hashed[:])
	if err != nil {
		return "", err
	}

	return base64.StdEncoding.EncodeToString(signature), nil
}

func (c *SeiDeployClient) makeRequest(endpoint string, data interface{}) (*http.Response, error) {
	payload, err := json.Marshal(data)
	if err != nil {
		return nil, err
	}

	signature, err := c.signPayload(data)
	if err != nil {
		return nil, err
	}

	req, err := http.NewRequest("POST", c.ServerURL+endpoint, bytes.NewBuffer(payload))
	if err != nil {
		return nil, err
	}

	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("X-Webhook-Signature", signature)

	return c.Client.Do(req)
}

func (c *SeiDeployClient) UpdateSubmodule(repository, branch string, project string) (map[string]interface{}, error) {
	data := map[string]interface{}{
		"repository": repository,
		"branch":     branch,
		"timestamp":  time.Now().Unix(),
	}

	if project != "" {
		data["project"] = project
	}

	resp, err := c.makeRequest("/api/webhook/submodule", data)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	var result map[string]interface{}
	err = json.NewDecoder(resp.Body).Decode(&result)
	return result, err
}

func (c *SeiDeployClient) Deploy(branch string) (map[string]interface{}, error) {
	data := map[string]interface{}{
		"branch":    branch,
		"timestamp": time.Now().Unix(),
	}

	resp, err := c.makeRequest("/api/webhook/deploy", data)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	var result map[string]interface{}
	err = json.NewDecoder(resp.Body).Decode(&result)
	return result, err
}

func (c *SeiDeployClient) GetStatus() (map[string]interface{}, error) {
	resp, err := c.Client.Get(c.ServerURL + "/api/status")
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	var result map[string]interface{}
	err = json.NewDecoder(resp.Body).Decode(&result)
	return result, err
}

func main() {
	client, err := NewSeiDeployClient("http://tu-servicio.com", "/path/to/private_key.pem")
	if err != nil {
		panic(err)
	}

	// Actualizar submódulo
	result, err := client.UpdateSubmodule("sei-compilados", "main")
	if err != nil {
		fmt.Printf("Error: %v\n", err)
	} else {
		fmt.Printf("Result: %v\n", result)
	}

	// Desplegar
	result, err = client.Deploy("main")
	if err != nil {
		fmt.Printf("Error: %v\n", err)
	} else {
		fmt.Printf("Result: %v\n", result)
	}

	// Verificar estado
	result, err = client.GetStatus()
	if err != nil {
		fmt.Printf("Error: %v\n", err)
	} else {
		fmt.Printf("Result: %v\n", result)
	}
}
```

## Ruby

### Cliente con `net/http`

```ruby
require 'net/http'
require 'json'
require 'openssl'
require 'base64'

class SeiDeployClient
  def initialize(server_url, private_key_path)
    @server_url = server_url.chomp('/')
    @private_key = OpenSSL::PKey::RSA.new(File.read(private_key_path))
  end

  def sign_payload(data)
    payload = JSON.generate(data)
    signature = @private_key.sign(OpenSSL::Digest::SHA256.new, payload)
    Base64.strict_encode64(signature)
  end

  def update_submodule(repository: 'sei-compilados', branch: 'main', project: nil)
    data = {
      repository: repository,
      branch: branch,
      timestamp: Time.now.to_i
    }

    if project
      data[:project] = project
    end

    make_request('/api/webhook/submodule', data)
  end

  def deploy(branch: 'main')
    data = {
      branch: branch,
      timestamp: Time.now.to_i
    }

    make_request('/api/webhook/deploy', data)
  end

  def get_status
    uri = URI(@server_url + '/api/status')
    response = Net::HTTP.get_response(uri)

    {
      status: response.code.to_i,
      body: JSON.parse(response.body)
    }
  end

  private

  def make_request(endpoint, data)
    uri = URI(@server_url + endpoint)
    payload = JSON.generate(data)
    signature = sign_payload(data)

    request = Net::HTTP::Post.new(uri)
    request['Content-Type'] = 'application/json'
    request['X-Webhook-Signature'] = signature
    request.body = payload

    response = Net::HTTP.start(uri.hostname, uri.port) do |http|
      http.request(request)
    end

    {
      status: response.code.to_i,
      body: JSON.parse(response.body)
    }
  end
end

# Uso
client = SeiDeployClient.new('http://tu-servicio.com', '/path/to/private_key.pem')

# Actualizar submódulo
result = client.update_submodule
puts result

# Desplegar
result = client.deploy
puts result

# Verificar estado
result = client.get_status
puts result
```

## Notas Importantes

### Parámetro `project` para Despliegue Automático

El parámetro opcional `project` en el endpoint `/api/webhook/submodule` habilita el despliegue automático de archivos de frontend:

**Comportamiento:**
- Si se especifica: después de un git pull exitoso, el sistema borra el contenido de `/var/www/{project}/*` y lo reemplaza con `sei-compilados/{project}/*`
- Si no se especifica: solo se actualiza el submódulo, sin despliegue

**Requisitos en el servidor:**
- La carpeta `sei-compilados/{project}` debe existir antes del despliegue
- La carpeta `/var/www/{project}` debe existir antes del despliegue
- **El sistema NO crea carpetas automáticamente** - ambas deben existir previamente

**Ejemplos de proyectos:**
- `pmr-frontend`
- `sagem-frontend`
- `sidi-frontend`

**Errores comunes:**
- 400 Bad Request: "Project folder not found" - La carpeta no existe en `sei-compilados`
- 400 Bad Request: "Destination folder not found" - La carpeta no existe en `/var/www`

### Encoding del Payload

Todos los clientes deben generar el payload JSON sin escapes de slashes:
- PHP: `JSON_UNESCAPED_SLASHES`
- Python: `separators=(',', ':')`
- JavaScript/Node: `JSON.stringify()` (por defecto)
- Go: `json.Marshal()` (por defecto)
- Ruby: `JSON.generate()` (por defecto)

### Timestamp

El timestamp debe ser Unix timestamp en segundos:
- PHP: `time()`
- Python: `int(time.time())`
- JavaScript: `Math.floor(Date.now() / 1000)`
- Go: `time.Now().Unix()`
- Ruby: `Time.now.to_i`

### Manejo de Errores

Todos los clientes deben manejar los siguientes códigos de estado:
- 200: Éxito
- 400: Error en el payload (verificar datos enviados)
- 401: Error de seguridad (firma inválida, timestamp inválido, etc.)
- 500: Error interno del servidor
