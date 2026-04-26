# 🌳 MadeiraLegal — Controle Inteligente de Madeira e DOF

> **O sistema que conecta conformidade ambiental, estoque e operação em um único painel.**

---

## O Problema

O setor madeireiro brasileiro opera sob regulação rigorosa do IBAMA via **DOF (Documento de Origem Florestal)**. Cada metro cúbico de madeira precisa ter rastreabilidade comprovada — da origem até a saída. Na prática:

- 📋 **Controle manual de DOFs** em planilhas, sujeito a erros e multas
- 📦 **Estoque físico desconectado** do saldo legal do DOF
- 🗺️ **Pátios sem visibilidade** sobre ocupação, capacidade e localização dos lotes
- 🔀 **Movimentações sem rastreio** — transferências, baixas e entradas sem histórico confiável
- 📊 **Relatórios manuais** para fiscalização, consumindo horas da equipe

> **Uma inconsistência entre estoque físico e saldo DOF pode gerar multas de R$ 500 a R$ 50 milhões e apreensão do estoque.**

---

## A Solução: MadeiraLegal

Uma plataforma web **SaaS multi-empresa** que unifica controle regulatório (DOF/IBAMA) e gestão operacional de estoque em tempo real.

### 🎯 Proposta de Valor

| Para | O MadeiraLegal oferece |
|------|----------------------|
| **Dono da madeireira** | Visão unificada de estoque, DOFs e operação em um dashboard |
| **Operador de pátio** | Mapa virtual interativo dos pátios com drag-and-drop de lotes |
| **Setor fiscal** | Relatórios PDF/Excel prontos para apresentar ao IBAMA |
| **Gestão** | Controle de permissões, cargos e múltiplas empresas |

---

## Funcionalidades Principais

### 📄 Gestão de DOFs
- Cadastro com número, série, validade e volume autorizado
- **Saldo calculado automaticamente** com status ATIVO → PARCIAL → ENCERRADO
- Itens por espécie florestal com quantidade autorizada e disponível
- Alocação de volumes DOF em lotes físicos do pátio

### 🗺️ Mapa Virtual de Pátios
- **Canvas interativo** com visualização em tempo real dos lotes
- Drag-and-drop para posicionar e redimensionar lotes
- Áreas bloqueadas configuráveis
- **Status visual por cores**: Disponível (verde), Reservado (laranja), Ocupado (vermelho), Bloqueado (cinza)
- Barra de ocupação por lote e do pátio inteiro

### 📦 Controle de Estoque Duplo
- **Estoque DOF (m³)**: controle legal vinculado ao documento
- **Estoque físico (UND)**: controle operacional por produto
- Volume unitário calculado automaticamente pelas dimensões do produto
- Saída inteligente: debita unidades físicas **e** m³ do DOF simultaneamente

### 🔀 Movimentações Rastreáveis
- **4 tipos**: Entrada, Transferência, Baixa e Ajuste
- Histórico imutável com usuário, data e volume
- Recalcula automaticamente saldo do DOF e volume do lote
- Preview de saída antes da confirmação

### 💰 Módulo de Vendas
- Pedidos com fluxo: Rascunho → Pendente → Finalizado/Cancelado
- Numeração sequencial automática
- Precificação flexível (unidade, metro linear, metro quadrado)
- Baixa automática no estoque ao finalizar e estorno ao cancelar
- Sistema de comissões por vendedor com relatórios

### 🔐 Segurança e Multi-Empresa
- **Multi-tenant**: isolamento total de dados por empresa
- 3 níveis de acesso: **Master** (plataforma) → **Admin** (empresa) → **Usuário** (cargo)
- Permissões granulares por cargo (ver, criar, editar, excluir)
- IDs criptografados em toda API
- Autenticação via token (Laravel Sanctum)
- Log de atividades com Spatie Activity Log

### 📊 Relatórios
- Exportação em **PDF e Excel**
- Relatório de DOFs com saldos
- Relatório de movimentações por período
- Relatório de comissões por vendedor

---

## Stack Tecnológica

| Camada | Tecnologia |
|--------|-----------|
| **Frontend** | React 18 + TypeScript + Vite |
| **Backend** | Laravel 11 + PHP 8.3 |
| **Banco de Dados** | PostgreSQL 16 |
| **Cache** | Redis 7 |
| **Infra** | Docker + Docker Compose + Nginx |
| **Auth** | Laravel Sanctum |
| **Auditoria** | Spatie Activity Log |

---

## Diferenciais Competitivos

| | Planilhas | Concorrentes genéricos | **MadeiraLegal** |
|---|:-:|:-:|:-:|
| Controle DOF integrado | ❌ | ⚠️ Parcial | ✅ Nativo |
| Mapa virtual de pátio | ❌ | ❌ | ✅ Interativo |
| Estoque duplo (m³ + UND) | ❌ | ❌ | ✅ Automático |
| Multi-empresa | ❌ | ⚠️ | ✅ Completo |
| Relatórios para IBAMA | ❌ | ⚠️ | ✅ PDF/Excel |
| Rastreabilidade completa | ❌ | ⚠️ | ✅ Imutável |

---

## Mercado-Alvo

- 🌲 **Madeireiras e serrarias** com operação regulada pelo IBAMA
- 🏭 **Indústrias de beneficiamento** de madeira
- 🪵 **Depósitos e distribuidores** de produtos florestais
- 📋 **Empresas que operam com DOF** e precisam de rastreabilidade

> **Brasil possui ~6.000 empresas com atividade registrada no setor madeireiro** que precisam de controle de DOF.

---

## Modelo de Negócio (Sugestão)

| Plano | Empresas | Usuários | Pátios | Preço/mês |
|-------|:--------:|:--------:|:------:|:---------:|
| **Starter** | 1 | 3 | 1 | R$ 199 |
| **Pro** | 1 | 10 | 5 | R$ 499 |
| **Enterprise** | Ilimitado | Ilimitado | Ilimitado | Sob consulta |

---

## Visão de Futuro

- 📱 App mobile para operadores de pátio
- 🔗 Integração direta com sistema DOF/IBAMA (SINAFLOR+)
- 📸 Fotos e QR Code por lote
- 📈 BI com dashboards analíticos avançados
- 🤖 Alertas inteligentes (DOF vencendo, estoque baixo, padrões suspeitos)
- 🌐 Integração com NF-e para rastreabilidade fiscal completa

---

> **MadeiraLegal** — Conformidade ambiental não precisa ser complicada. 🌳
