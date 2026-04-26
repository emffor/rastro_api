# Walkthrough: Módulo de Vendas

## O que foi implementado

### 1. Clientes
- CRUD completo (`/api/clientes`)
- Campos: nome, tipo (PF/PJ), documento (opcional), email, telefone, endereço

### 2. Pedidos
- Fluxo: `RASCUNHO → PENDENTE → FINALIZADO` ou `CANCELADO`
- Número sequencial automático (PED-2026-00001)
- Itens com cálculo automático de subtotal

### 3. Precificação de Produtos
Novos campos: `preco_compra`, `preco_venda`, `tipo_preco`

| Tipo | Cálculo |
|------|---------|
| UNIDADE | Preço × Quantidade |
| METRO_LINEAR | Preço × Comprimento × Quantidade |
| METRO_QUADRADO | Preço × Largura × Comprimento × Quantidade |

### 4. Comissões
- Campo `comissao_percentual` no cadastro do vendedor
- Calculada automaticamente ao criar pedido
- Relatório por período e por vendedor

### 5. Estoque Automático
| Ação | Efeito no Estoque |
|------|-------------------|
| Finalizar pedido | ⬇️ Baixa automática |
| Cancelar pedido | ⬆️ Estorno automático |

---

## Rotas Principais

```
# Clientes
GET/POST   /api/clientes
GET/PUT/DEL /api/clientes/{id}

# Pedidos
GET/POST   /api/pedidos
GET/PUT    /api/pedidos/{id}
POST       /api/pedidos/{id}/itens
POST       /api/pedidos/{id}/enviar
POST       /api/pedidos/{id}/finalizar
POST       /api/pedidos/{id}/cancelar

# Comissões
GET        /api/comissoes?data_inicio=&data_fim=
GET        /api/comissoes/vendedor/{id}
```

---

## Teste Realizado

1. Criado cliente "Cliente Teste"
2. Criado pedido PED-2026-00001 com 5 unidades
3. Enviado para aprovação (PENDENTE)
4. Finalizado → **Estoque: 118 → 113** ✅
