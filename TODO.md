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
- [ ] **Monitorização:** keywords das primeiras regras (ex. "Universidade do Porto", "U.Porto", nome do Reitor…) — introduzidas pelos managers em Definições → Monitorização

## 1. Setup

- [x] `git init`
- [x] Repositório remoto no GitHub (github.com/pedrocandeias/cidash, público)
- [x] Pacote de deploy `dist/` (`scripts/build-dist.sh`), gerado também pelo GitHub Actions como artefacto após o CI
- [ ] Primeira instalação no servidor (ARCHITECTURE §7)
- [x] Projeto Laravel 13 + starter kit React, DDEV (SQLite, sem contentor de BD, filas no driver `database`)
- [x] Configuração SQLite (WAL, busy_timeout, foreign_keys, transações IMMEDIATE)
- [x] Backup diário `cidash:backup` (`VACUUM INTO`, retenção de 14)
- [ ] Cópia dos backups para fora do servidor (depende do servidor), incluindo os ficheiros em `storage/app/private` (anexos e fotografias), que o `cidash:backup` não copia
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
- [x] Marca provisória: logótipo, favicon e ícone neutros (substituem o Laravel)
- [x] Identidade visual definitiva: logótipo e cores (Design System "CIDASH" no Claude Design, aprovado a 2026-09-24)
- [x] Auth local (Fortify): convites por email ou link copiado (broker `invites`, 7 dias, `/invitation/{token}`), `activated_at`, recuperar password, rate limiting, `uncompromised`, sem registo público
- [x] 2FA TOTP opcional para todos (ativar/desativar no perfil, códigos de recuperação), vindo do starter kit
- [x] Reposição do 2FA de um utilizador pelo super admin (com registo no `activity_log`)
- [x] Tabela `settings` + `MailSettings` (password cifrada) + painel Administração → Email: SMTP, remetente, email de teste (com a configuração guardada); mailer configurado ao ser resolvido
- [x] Papéis `manager`/`editor`/`member` por workspace (`WorkspaceRole`, cumulativos) + `is_super_admin`
- [x] Regra de pelo menos 1 manager (`TeamMembers`), gates `super-admin` e `manage-members`
- [x] Policies segundo a matriz §2.6, à medida que cada módulo é feito (contínuo: cada módulo novo traz as suas)
- [x] "Eliminar conta": o único manager de uma equipa não pode eliminar a conta
- [x] Gestão de membros pelos managers (Definições → Equipa): adicionar por email (conta nova com convite; conta existente entra logo), mudar papel, remover, reenviar convite
- [x] Área de Administração do super admin: Email
- [x] Administração: lista global de utilizadores (desativar/reativar, super admin, 2FA, equipas e papéis)
- [x] Registo no `activity_log` dos acessos do super admin a workspaces de que não é membro (uma vez por sessão)
- [x] Workspaces: `workspaces`, `workspace_user`, `users.current_workspace_id`, middleware `workspace` + `WorkspaceContext`, seed "CI Reitoria"
- [x] Scope global de workspace (`WorkspaceScope`, falha sem workspace ativo)
- [x] Suite de testes de isolamento entre workspaces (contínuo: cada módulo novo traz os seus)
- [x] Núcleo: `objects` (`Record`), `relations` (`Link`, só dentro do workspace), `tags`, `comments`, `activity_log`; trait `IsRecord`; serviços `Links`, `Tags`, `Terms`
- [x] Notificações in-app (tabela `notifications`, sino, abrir muda para o workspace da notificação)
- [x] `reminders` + `cidash:send-reminders` (notificação in-app)
- [x] Registo de tipos: `App\Core\RecordTypes` (alias, modelo, rota, rótulo) → *morph map*
- [x] Metadados por tipo para a pesquisa (`$searchable` em cada modelo)
- [x] Componentes partilhados: CommentsThread (comentários em qualquer registo), ActivityFeed, NotificationsMenu (sino)
- [x] Componentes partilhados: RelationsPanel (com pesquisa por título), RemindersPanel, CreateTaskButton, TagInput
- [x] Componentes partilhados: ObjectDrawer (pré-visualização lateral): relações, campanhas e calendário
- [x] Calendar: CRUD; vistas mês, semana, dia e agenda; cores por tipo; tags; lembretes; relações; criar tarefa a partir do evento
- [x] Calendar: filtros (tipo, responsável, campanha), arrastar e redimensionar eventos
- [x] Notice Board: CRUD, fixados (só managers), publicação agendada, expiração, arquivo
- [x] Tasks: CRUD, minhas/equipa, filtros de estado, conclusão rápida, detalhe com comentários e histórico, apagar (criador ou manager), origem (`source_id` + relação `originated_from`), notificação in-app de atribuição
- [x] Tasks: vista Kanban por estado
- [x] Press Requests: CRUD, lista por prazo com semáforo, autocompletar jornalista e meio, data de resposta, lembretes, criar tarefa
- [x] Press Requests: sugerir especialistas (relacionar o pedido com Pessoas de interesse no painel de relações)
- [x] Content Pipeline: Kanban com 7 estados, drag & drop, `stage_changed_at`, aprovação só por editors, managers e super admin (sem saltar a revisão); notificação aos editors e managers quando um conteúdo entra em Review
- [x] Content Pipeline: vista de lista e calendário editorial (publish_at)
- [x] Campaigns: CRUD, responsáveis, secção "Nesta campanha" (eventos e conteúdos via `part_of`)
- [x] Termos normalizados (backend): `Terms`, `normalized_name` + índice único, reutilizar em vez de duplicar, sugestões (distância ≤ 2), testes
- [x] Termos normalizados (UI): TagInput com sugestões e "Queria dizer…?"
- [x] Gestão de tags e áreas pelos managers: renomear, fundir, apagar (Definições → Termos)
- [x] Pessoas de interesse: CRUD de perfis, `expertise_areas`, pesquisa por keyword + dropdown de área, cartões, "Copiar para enviar", aviso de bio por rever, fotografia privada
- [x] Search global atrás de uma interface (`Core/Search`), implementação FTS5 (unicode61 remove_diacritics, prefixos, bm25), paleta ⌘K, `cidash:search-reindex`
- [x] Home: widgets de eventos, avisos, reminders, tarefas, imprensa e conteúdos; vista de equipa para managers; aprovações pendentes para editors
- [x] Seeds de demonstração realistas (`DemoSeeder`, só em ambiente local)

## 3. MVP-2 — Monitorização

- [x] `sources` (catálogo global, super admin) + `workspace_sources` (subscrição, prioridade); estado, último erro, `consecutive_failures`
- [x] Verificar RSS de JN, DN, Observador, Público e Expresso (JN, DN, Observador, Público e SAPO com RSS; Expresso bloqueia pedidos automáticos → via Google News)
- [x] Scraper genérico configurável (páginas de secção → artigos → OpenGraph), com robots.txt, User-Agent identificado e rate limit por domínio
- [x] SAPO Notícias (agregador): deduplicação por URL canónico e agrupamento em histórias
- [x] Google News RSS (pesquisa); título sem o meio, agrupado na mesma história que o artigo original
- [x] Google News RSS por regra de monitorização (com as regras)
- [x] Processamento de notícias: normalizar, URL canónico, dedup por hash, `news_items` só com metadados (sem texto integral)
- [x] Agrupamento em stories (trigramas no título, 72 h)
- [x] `news_item_states`: triagem e relevância por workspace (tipo de registo `news`)
- [x] `monitoring_rules`: include/exclude terms, pessoa de interesse opcional → `mentions` por workspace
- [x] Inboxes de triagem News e Mentions (relevante / irrelevante / criar tarefa / relacionar)
- [x] Alerts: motor + regras do catálogo (incluindo `source_failing`), parâmetros editáveis, faixa no Home e 🔔
- [x] Briefing diário determinístico (página + arquivo)
- [x] Widgets de notícias e menções no Home

## 4. Phase 2

- [ ] AI Assistant: tools estruturadas (incl. `find_people`) + FTS5, citações validadas, painel contextual (confirmar antes o envio de dados para a API) — **bloqueado:** chave de API e aprovação institucional para enviar dados
- [x] Briefing semanal (segundas-feiras: semana que começa e revisão da anterior)
- [ ] Resumo por IA no briefing diário e semanal — **bloqueado:** depende do assistente de IA
- [ ] Pesquisa semântica (embeddings; `sqlite-vec` ou similaridade em PHP) e melhor agrupamento em stories — **bloqueado:** precisa de um modelo de embeddings (mesma decisão que a IA)
- [x] Alerta `mention_spike`
- [x] Notificações e briefing por email (preferências por utilizador; envio quando o SMTP estiver configurado)
- [x] Assets/anexos
- [x] ICS export/subscrição
- [x] Estados e tipos configuráveis: tipos de evento e formatos de conteúdo por equipa (os estados dos fluxos ficam fixos, ver ARCHITECTURE)
- [x] Scoring de relevância (determinístico: regras, pessoas de interesse, fontes prioritárias, cobertura)
- [ ] Mais fontes (outros meios; APIs se o scraping não chegar) — **à espera da equipa:** que meios acrescentar (o super admin já os pode acrescentar em Administração → Fontes)

## 5. Phase 3 — Expansão às UOs

- [ ] Avaliar o piloto: volume, contenção de escrita e feedback. Decidir a migração para MariaDB/MySQL — **depois do piloto**
- [ ] (se sim) Implementação de `Core/Search` para o novo motor + script e teste de migração de dados
- [ ] SSO U.Porto — **bloqueado:** acesso ao fornecedor de identidade da U.Porto
- [x] Seletor de workspace
- [x] Administração completa do super admin: criar workspaces, nomear managers, visão geral de todos os workspaces e da ingestão
- [x] Onboarding por UO (fontes subscritas, regras, áreas de especialidade, guia)

## 6. Future

- [ ] Directory institucional (unidades, centros, projetos) com sincronização SIGARRA
- [ ] Sincronização do calendário com Microsoft 365
- [ ] Redes sociais
- [ ] Sentimento, relatórios e analytics
- [ ] Dashboard personalizável
- [ ] PWA e integração com Teams/Slack
- [ ] Construtor genérico de regras de alerta
