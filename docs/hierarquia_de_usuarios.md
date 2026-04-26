# Walkthrough: Sistema Multi-Tenant + Autenticação

## Resumo

Sistema completo com isolamento de dados por empresa.

## Hierarquia

| Tipo    | Cargo? | Acesso              |
| ------- | ------ | ------------------- |
| MASTER  | ❌     | Todas empresas      |
| ADMIN   | ❌     | Tudo na empresa     |
| USUARIO | ✅     | Permissões do cargo |

## Rotas de Auth

| Rota                            | Descrição             |
| ------------------------------- | --------------------- |
| `POST /api/auth/login`          | Login (retorna token) |
| `POST /api/auth/logout`         | Logout                |
| `GET /api/auth/me`              | Dados do usuário      |
| `POST /api/auth/trocar-empresa` | MASTER troca empresa  |

## Credenciais MASTER

```
Email: master@rastro.com
Senha: master123
```

## Teste de Login

```bash
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "master@rastro.com", "password": "master123"}'
```

**Resposta:**

```json
{
    "mensagem": "Login realizado com sucesso.",
    "dados": {
        "usuario": { "name": "Master Admin", "is_master": true },
        "token": "1|JegVowVPYXuNC0szZkej..."
    }
}
```

## Próximos Passos

- Usar token nas requisições: `Authorization: Bearer {token}`
- MASTER pode criar empresas via `POST /api/empresas`
- ADMIN cria cargos e usuários
