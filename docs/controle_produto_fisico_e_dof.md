# Walkthrough: Reestruturação DOF + Estoque

## Resumo da Mudança

Implementada nova estrutura com **dois estoques separados**:

| Estoque    | Unidade | Propósito              |
| ---------- | ------- | ---------------------- |
| **DOF**    | M³      | Controle legal (IBAMA) |
| **Físico** | UND     | Controle operacional   |

---

## Nova Estrutura de Dados

```
DOF (Documento)
├── DofItem: Mogno Viga (50 M³)
│   ├── Produto: LINHA MOGNO 5.0 X 12 X 5.00 (100 UND)
│   └── volume_unitario: 0.03 M³/und (calculado automaticamente)
├── DofItem: Mogno Prancha (30 M³)
│   └── Produto: PRANCHA MOGNO 8 X 30 X 3.00 (50 UND)
└── DofItem: Jacarandá Viga (20 M³)
```

---

## Fluxo de Saída

1. Vende **10 UND** de LINHA MOGNO
2. Sistema debita **10 UND** do estoque físico
3. Calcula: `5cm × 12cm × 5m × 10 = 0.30 M³`
4. Debita **0.30 M³** do saldo do item do DOF

---

## Arquivos Criados/Modificados

- [2026_01_08_111500_create_dof_itens_table.php](file:///home/emffor/xxx/rastro_api/database/migrations/2026_01_08_111500_create_dof_itens_table.php)
- [DofItem.php](file:///home/emffor/xxx/rastro_api/app/Models/DofItem.php)
- [Produto.php](file:///home/emffor/xxx/rastro_api/app/Models/Produto.php) (dimensões + volume automático)
- [MovimentacaoEstoqueService.php](file:///home/emffor/xxx/rastro_api/app/Services/MovimentacaoEstoqueService.php) (nova lógica)

---

## Verificação

✅ **DOF com itens**:

```json
{
    "numero": "DOF-2026-0001",
    "itens": [
        {
            "tipo": "VIGA",
            "especie": "Mogno",
            "quantidade_disponivel": "50.0000"
        },
        {
            "tipo": "PRANCHA",
            "especie": "Mogno",
            "quantidade_disponivel": "30.0000"
        }
    ]
}
```

✅ **Produto com volume calculado**:

```json
{
    "nome": "LINHA MOGNO 5.0 X 12 X 5.00",
    "largura": "5.00",
    "espessura": "12.00",
    "comprimento": "5.00",
    "volume_unitario": "0.030000",
    "estoque_quantidade": "100.0000"
}
```
