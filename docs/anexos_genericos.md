# Anexos genéricos

Este módulo centraliza anexos em S3 com relacionamento polimórfico.

## Endpoints

- `POST /api/anexos/upload`
- `DELETE /api/anexos/{relacionavelId}`
- `GET /api/anexos/por-entidade?entidade_type=...&entidade_id=...`
- `GET /api/anexos/{anexoId}/url`

## Categorias administrativas

- `GET /api/admin/anexo-categorias`
- `GET /api/admin/anexo-categorias/ativas`
- `POST /api/admin/anexo-categorias`
- `PUT /api/admin/anexo-categorias/{id}`
- `DELETE /api/admin/anexo-categorias/{id}`

## Payload de upload

```json
{
  "file": "arquivo.pdf",
  "categoria_slug": "dof",
  "entidade_type": "App\\Models\\Dof",
  "entidade_id": "uuid-ou-id-criptografado",
  "campo": "opcional"
}
```

## Convenções

- O arquivo deve ser PDF.
- A URL temporária é cacheada por 110 minutos no Redis.
- O relacionamento entre anexo e entidade fica em `anexos_relacionaveis`.
- A categoria define o limite mensal e o tamanho máximo aceito.
