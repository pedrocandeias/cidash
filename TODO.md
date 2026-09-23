# TODO — CIDASH

Âmbito e justificações: ver `ARCHITECTURE.md`. Este ficheiro é o backlog acionável.

## 0. Decisões antes de DEV

### Decidido
- [x] **Base de dados:** SQLite (motivo: mobilidade; instância = pasta da app + pasta de dados)
- [x] **Stack:** PHP, Laravel 13 + Inertia/React (starter kit oficial) (ARCHITECTURE §7, *Porquê Laravel*)
- [x] **Autenticação:** contas locais por convite; 2FA opcional para todos; SSO U.Porto na Phase 3
- [x] **Email:** configurável num painel da Administração (SMTP + remetente + email de teste)
- [x] **Alojamento:** servidor LAMP acessível pela web, com SSH, cron e DocumentRoot configurável
- [x] **Papéis:** super admin (faz tudo), `manager` (1 ou vários por equipa, adiciona utilizadores só ao seu workspace), `editor` (aprova conteúdos) e `member`; sem `viewer`; piloto com ~20 utilizadores (ARCHITECTURE §2.6)
- [x] **Multi-UO:** uma instância, workspaces **estanques**; nada visível entre equipas, exceto para o super admin
- [x] **News e Mentions em tabelas separadas**
- [x] **Directory institucional:** adiado (Future). Substituído por **Pessoas de interesse** (perfis de especialistas introduzidos manualmente)
- [x] **Fontes iniciais:** JN, DN, Observador, Público, Expresso, SAPO Notícias, Google News; sem clipping contratado
- [x] **Idioma:** interface em português, preparada para tradução desde o início
- [x] **Prioridade:** Phase 2 (AI Assistant) antes da Phase 3 (Expansão às UOs)
- [x] **RGPD:** contactos profissionais; interesse legítimo; corrigir ou apagar a pedido

### Em aberto (não bloqueiam o arranque)
- [ ] **Email:** dados do servidor SMTP a introduzir no painel quando estiverem disponíveis
- [ ] **Servidor:** confirmar as versões de PHP (≥ 8.3) e SQLite (≥ 3.45, com FTS5)
- [ ] **Pessoas de interesse:** lista inicial de áreas de especialidade; há perfis existentes (folha de cálculo, Word) para importar?
- [ ] **Monitorização:** keywords das primeiras regras (ex. "Universidade do Porto", "U.Porto", nome do Reitor…)

## 1. Setup

- [x] `git init`
- [x] Repositório remoto no GitHub (github.com/pedrocandeias/cidash, público)
- [x] Pacote de deploy `dist/` (`scripts/build-dist.sh`), gerado também pelo GitHub Actions como artefacto após o CI
- [ ] Primeira instalação no servidor (ARCHITECTURE §7)
- [x] Projeto Laravel 13 + starter kit React, DDEV (SQLite, sem contentor de BD, filas no driver `database`)
- [x] Configuração SQLite (WAL, busy_timeout, foreign_keys, transações IMMEDIATE)
- [x] Backup diário `cidash:backup` (`VACUUM INTO`, retenção de 14)
- [ ] Cópia dos backups para fora do servidor (depende do servidor)
- [x] i18n: `lang/pt_PT.json` (chave = texto em inglês) partilhado via Inertia, hook `useTranslation`, mensagens do Laravel em pt europeu
- [x] Lint/format, testes, CI (Pint, PHPStan, vp check, tsc, `composer audit`)
- [x] Deploy em LAMP: `dist/`, cron `schedule:run`, fila via scheduler (por testar num servidor real)
- [x] Layout base: sidebar agrupada (§3), breadcrumbs e menu do utilizador traduzidos, tema claro/escuro do starter kit
- [x] `CLAUDE.md` com comandos

## 2. MVP-1 — Núcleo operacional

- [x] Traduzir as páginas do starter kit (auth, definições, 2FA) com `t()`
- [x] Passkeys desligadas (config Fortify, UI de login, confirmação e gestão, rota `.well-known`); o pacote e a migração ficam para ser fácil religar
- [x] Desligar o registo público e a verificação de email do Fortify; `/` redireciona para o dashboard
- [x] Comandos de arranque: `cidash:create-workspace`, `cidash:create-user` (link para definir a palavra-passe)
- [ ] Identidade visual: logótipo CIDASH (substituir o do Laravel), cores
- [x] Auth local (Fortify): convites por email ou link copiado (broker `invites`, 7 dias, `/invitation/{token}`), `activated_at`, recuperar password, rate limiting, `uncompromised`, sem registo público
- [x] 2FA TOTP opcional para todos (ativar/desativar no perfil, códigos de recuperação), vindo do starter kit
- [ ] Reposição do 2FA de um utilizador pelo super admin (com registo no `activity_log`)
- [x] Tabela `settings` + `MailSettings` (password cifrada) + painel Administração → Email: SMTP, remetente, email de teste (com a configuração guardada); mailer configurado ao ser resolvido
- [x] Papéis `manager`/`editor`/`member` por workspace (`WorkspaceRole`, cumulativos) + `is_super_admin`
- [x] Regra de pelo menos 1 manager (`TeamMembers`), gates `super-admin` e `manage-members`
- [ ] Policies segundo a matriz §2.6, à medida que cada módulo é feito
- [x] "Eliminar conta": o único manager de uma equipa não pode eliminar a conta
- [x] Gestão de membros pelos managers (Definições → Equipa): adicionar por email (conta nova com convite; conta existente entra logo), mudar papel, remover, reenviar convite
- [x] Área de Administração do super admin: Email
- [ ] Administração: lista global de utilizadores (desativar conta, super admin)
- [x] Registo no `activity_log` dos acessos do super admin a workspaces de que não é membro (uma vez por sessão)
- [x] Workspaces: `workspaces`, `workspace_user`, `users.current_workspace_id`, middleware `workspace` + `WorkspaceContext`, seed "CI Reitoria"
- [x] Scope global de workspace (`WorkspaceScope`, falha sem workspace ativo)
- [ ] Suite de testes de isolamento entre workspaces (cresce com cada módulo)
- [x] Núcleo: `objects` (`Record`), `relations` (`Link`, só dentro do workspace), `tags`, `comments`, `activity_log`; trait `IsRecord`; serviços `Links`, `Tags`, `Terms`
- [ ] `reminders` e notificações in-app (com o primeiro módulo que os use)
- [x] Registo de tipos: *morph map* (alias = `objects.type`)
- [ ] Metadados por tipo (label, ícone, rota, campos pesquisáveis), com a pesquisa e o painel de relações
- [ ] Componentes partilhados: ObjectDrawer, RelationsPanel, CreateTaskAction, CommentsThread, ActivityFeed
- [ ] Calendar: CRUD; vistas mês, semana, dia e lista; filtros; reminders
- [ ] Notice Board: CRUD, pinned, expiração
- [ ] Tasks: CRUD, criar a partir de qualquer objeto, minhas/equipa, comentários
- [ ] Press Requests: CRUD, lista por deadline com semáforo, autocompletar jornalista e meio, relacionar especialistas
- [ ] Content Pipeline: Kanban com 7 estados, drag & drop, `stage_changed_at`, Review → Approved só por editors, managers e super admin; notificação aos editors e managers quando um conteúdo entra em Review
- [ ] Campaigns: CRUD, responsáveis, relações com eventos e conteúdos
- [x] Termos normalizados (backend): `Terms`, `normalized_name` + índice único, reutilizar em vez de duplicar, sugestões (distância ≤ 2), testes
- [ ] Termos normalizados (UI): combobox com sugestões, aviso "Queria dizer…?", renomear/fundir/apagar pelos managers
- [ ] Pessoas de interesse: CRUD de perfis, `expertise_areas`, pesquisa por keyword + dropdown de área, cartões, "Copiar para enviar", aviso de bio por rever
- [ ] Search global atrás de uma interface (`Core/Search`), implementação FTS5 (unicode61 remove_diacritics + trigram), paleta ⌘K
- [ ] Home: widgets de eventos, avisos, reminders, tarefas, imprensa e conteúdos; vista de equipa para managers; aprovações pendentes para editors
- [ ] Seeds de demonstração realistas

## 3. MVP-2 — Monitorização

- [ ] `sources` (catálogo global, super admin) + `workspace_sources` (subscrição, prioridade); estado, último erro, `consecutive_failures`
- [ ] Verificar RSS de JN, DN, Observador, Público e Expresso; configurar o scraper genérico onde não houver
- [ ] Scraper genérico configurável (páginas de secção → artigos → OpenGraph/JSON-LD), com robots.txt, User-Agent identificado e rate limit por domínio
- [ ] SAPO Notícias (agregador): deduplicar pelo URL do meio original
- [ ] Google News RSS por regra de monitorização; resolver os redirecionamentos para o URL original (fallback: título + meio)
- [ ] Processamento de notícias: normalizar, URL canónico, dedup por hash, `news_items` só com metadados (sem texto integral)
- [ ] Agrupamento em stories (trigramas no título)
- [ ] `news_item_states`: triagem e relevância por workspace
- [ ] `monitoring_rules`: include/exclude terms, pessoa de interesse opcional → `mentions` por workspace
- [ ] Inboxes de triagem News e Mentions (relevante / irrelevante / criar tarefa / relacionar)
- [ ] Alerts: motor + regras do catálogo (incluindo `source_failing`), parâmetros editáveis, faixa no Home e 🔔
- [ ] Briefing diário determinístico (página + arquivo)
- [ ] Widgets de notícias e menções no Home

## 4. Phase 2

- [ ] AI Assistant: tools estruturadas (incl. `find_people`) + FTS5, citações validadas, painel contextual (confirmar antes o envio de dados para a API)
- [ ] Resumo por IA no briefing diário; briefing semanal
- [ ] Pesquisa semântica (embeddings; `sqlite-vec` ou similaridade em PHP) e melhor agrupamento em stories
- [ ] Alerta `mention_spike`
- [ ] Notificações e briefing por email
- [ ] Assets/anexos
- [ ] ICS export/subscrição
- [ ] Estados e tipos configuráveis
- [ ] Scoring de relevância
- [ ] Mais fontes (outros meios; APIs se o scraping não chegar)

## 5. Phase 3 — Expansão às UOs

- [ ] Avaliar o piloto: volume, contenção de escrita e feedback. Decidir a migração para MariaDB/MySQL
- [ ] (se sim) Implementação de `Core/Search` para o novo motor + script e teste de migração de dados
- [ ] SSO U.Porto
- [ ] Seletor de workspace
- [ ] Administração completa do super admin: criar workspaces, nomear managers, visão geral de todos os workspaces e da ingestão
- [ ] Onboarding por UO (fontes subscritas, regras, áreas de especialidade, guia)

## 6. Future

- [ ] Directory institucional (unidades, centros, projetos) com sincronização SIGARRA
- [ ] Sincronização do calendário com Microsoft 365
- [ ] Redes sociais
- [ ] Sentimento, relatórios e analytics
- [ ] Dashboard personalizável
- [ ] PWA e integração com Teams/Slack
- [ ] Construtor genérico de regras de alerta
