# CIDASH — Arquitetura

**CIDASH — Communication & Image Dashboard.** Aplicação web interna do Serviço de Comunicação e Imagem da Universidade do Porto.

Piloto: **CI da Reitoria (~20 utilizadores).** Depois de testado, está prevista a expansão aos serviços de comunicação das unidades orgânicas (faculdades). Cada equipa terá o seu workspace, **completamente estanque** (ver §2.5).

Estado: **proposta pré-desenvolvimento.** Ainda não existe código. As decisões marcadas com ❓ estão em aberto (ver `TODO.md` → *Decisões antes de DEV*).

O documento segue a ordem pedida: funcional → dados → navegação → dashboard → âmbito → técnica.

---

## 1. Arquitetura funcional

O CIDASH é um **workspace operacional** e não apenas um dashboard. O princípio central: **qualquer registo é um objeto que pode ser relacionado com outros objetos, pesquisado, comentado e transformado em tarefa.**

### Camadas

```
┌────────────────────────────────────────────────────────────────────┐
│  EXPERIÊNCIA   Home · Briefing · Search · AI Assistant             │  consome tudo
├────────────────────────────────────────────────────────────────────┤
│  OPERAÇÃO      Calendar · Tasks · Content Pipeline · Campaigns     │  trabalho da equipa
│                Press Requests · Notice Board · Pessoas de interesse│
├────────────────────────────────────────────────────────────────────┤
│  MONITORIZAÇÃO News Monitor · Online Mentions · Alerts             │  entrada de informação
│                (Sources, Monitoring Rules, Alert Rules)            │
├────────────────────────────────────────────────────────────────────┤
│  NÚCLEO        Objects · Relations · Tags · Comments · Activity ·  │  partilhado por
│                Workspaces · Users/Roles · Reminders · Notifications│  todos os módulos
└────────────────────────────────────────────────────────────────────┘
```

### Capacidades transversais (vêm do núcleo, não de cada módulo)

| Capacidade | Descrição |
|---|---|
| **Relacionar** | Ligar qualquer objeto a qualquer objeto do mesmo workspace (evento ↔ campanha, notícia ↔ pessoa de interesse…). O painel de relações é igual em todos os ecrãs de detalhe. |
| **Criar tarefa a partir de…** | Ação presente em todos os objetos. A tarefa guarda a origem (`source_object_id`) e fica também relacionada. |
| **Comentar / histórico** | Comentários e registo de atividade em qualquer objeto. |
| **Tags** | Etiquetas livres e leves. |
| **Reminders** | Lembretes por utilizador sobre qualquer objeto com data. |
| **Pesquisa** | Um único índice sobre todos os objetos. |
| **Citável pela IA** | Todos os objetos têm um ID estável, pelo que as respostas da IA podem sempre referenciar os registos usados. |

### Fluxos principais

**A. Ingestão (News + Mentions)**
```
Source (scraper de media online / RSS / Google News RSS)
  → fetch agendado (fila)
  → normalização (URL canónico, texto, data, idioma)
  → deduplicação exata (hash do URL canónico)
  → agrupamento em "story" (mesmo acontecimento) por similaridade de título
  → para cada workspace que subscreve a fonte: estado de triagem "new"
  → matching das regras de monitorização de cada workspace → mentions
  → avaliação de alertas
  → aparece no Home / Briefing
```
Os scrapers seguem as regras da §2.3 (robots.txt, ritmo limitado, só metadados). Não há clipping contratado, por isso são a fonte principal de notícias.

**B. Triagem → ação**
Notícia ou menção nova → revista (relevante / irrelevante) → opcionalmente relacionada com um evento, campanha ou pessoa de interesse → opcionalmente gera uma tarefa ou um conteúdo no pipeline.

**C. Planeamento**
Campanha → eventos + conteúdos (pipeline) + tarefas → alertas vigiam lacunas (campanha sem conteúdos, evento sem responsável).

**D. Resposta à imprensa**
Pedido recebido → responsável atribuído → (se preciso) procurar em *Pessoas de interesse* um especialista e enviar a bio ao jornalista → tarefas internas → resposta → fecho. Alertas sobre deadlines.

**E. Síntese**
Briefing diário/semanal = consultas determinísticas sobre os dados, com resumo por IA opcional por cima. O AI Assistant responde a perguntas usando apenas dados do CIDASH e cita sempre os registos.

---

## 2. Entidades e relações

### 2.1 Decisão central: registo único de objetos + relações genéricas

Todos os registos de domínio (eventos, notícias, tarefas, pessoas de interesse…) têm uma linha numa tabela **`objects`**, que partilha o `id` com a tabela específica do tipo (*class table inheritance*).

```
objects (id uuid PK, workspace_id, type, title, created_by, created_at, updated_at, archived_at)
objects_fts (FTS5: title + texto pesquisável de cada tipo)
   ▲ 1:1 (mesmo id)
   ├── events
   ├── notices
   ├── news_item_states  (tipo "news": a notícia vista por um workspace)
   ├── mentions
   ├── content_items
   ├── tasks
   ├── press_requests
   ├── campaigns
   └── people            (Pessoas de interesse)

news_items               (partilhados a nível técnico; ver §2.3 e §2.5)
relations (source_id → objects, target_id → objects, relation_type, note, created_by, created_at)
```

**Porquê esta abordagem:**
- As relações têm integridade referencial real (FK para `objects`), ao contrário de colunas polimórficas `*_type`/`*_id`.
- Pesquisa global, comentários, tags, reminders, tarefas e citações da IA funcionam da mesma forma para todos os tipos.
- **Um módulo novo** = uma tabela nova + um tipo registado. O núcleo não muda (requisito de extensibilidade).

**Custo:** um join extra e a obrigação de criar/atualizar `objects` na mesma transação. Aceitável; fica encapsulado numa camada de serviço.

**Implementação** (`app/Core`, `app/Models`)
- **Modelos:**
  - `Record` corresponde a `objects`, porque o PHP reserva `Object`;
  - `Link` corresponde a `relations`, para não colidir com a classe `Relation` do Eloquent;
  - `Tag`, `Comment` e `Activity` (`activity_log`).
- **Trait `IsRecord`:** um modelo de domínio que o use cria o seu `Record` no workspace ativo, mantém o título sincronizado, regista criado/alterado/apagado no `activity_log` e fica filtrado pelo workspace. O tipo é o alias do *morph map*, e `Record::subject` devolve o modelo de domínio.
- **`WorkspaceScope`:** sem workspace ativo, as consultas **dão erro** em vez de devolverem dados de todas as equipas. Para as contornar é preciso `withoutGlobalScope(WorkspaceScope::class)`, que fica visível em revisão.
- **Serviços:**
  - `Links`: ligar, desligar e listar nos dois sentidos, só dentro do mesmo workspace;
  - `Tags`: nunca duplica e sugere termos parecidos;
  - `Terms`: normalização reutilizável.

**Alternativa rejeitada:** um grafo genérico (EAV ou base de dados de grafos). É demasiado flexível, perde tipagem e validação e complica os relatórios.

### 2.2 Núcleo

| Tabela | Campos principais | Notas |
|---|---|---|
| `workspaces` | name, slug | Uma equipa de CI. No piloto só existe a Reitoria (§2.5) |
| `users` | name, email, locale, active, activated_at (null = convite por aceitar), is_super_admin, current_workspace_id (workspace ativo), two_factor_secret, two_factor_recovery_codes, two_factor_confirmed_at | Ver §2.6 e *Autenticação* (§7) |
| `workspace_user` | workspace_id, user_id, role | Role por workspace: `manager`, `editor` ou `member` (§2.6). Cada workspace tem sempre ≥ 1 manager |
| `objects` | id, **workspace_id**, type, title, created_by, timestamps, archived_at | Registo universal. Tudo pertence a um workspace |
| `objects_fts` | object_id, title, body | Tabela virtual FTS5 para a pesquisa global |
| `relations` | source_id, target_id, relation_type, note | Único por (source, target, type). Só entre objetos do mesmo workspace. Mostradas nos dois sentidos na UI |
| `tags` / `object_tags` | workspace_id, name, normalized_name | Etiquetas livres. Proteção contra duplicados como nas áreas de especialidade (ver *Termos normalizados*) |
| `comments` | object_id, author_id, body | Serve os `comments` das tarefas e de tudo o resto |
| `activity_log` | object_id, user_id, action, changes (json) | Auditoria e "histórico" |
| `reminders` | object_id, user_id, remind_at, channel, sent_at | Serve os `reminders` dos eventos e de outros objetos |
| `notifications` | user_id, object_id, alert_id, kind, read_at | Entregas in-app; por email quando o SMTP estiver configurado |
| `settings` | key, value (json), is_encrypted | Configuração da instância editável na Administração (ex. email). Valores sensíveis cifrados com a `APP_KEY` |

**Tipos de relação (catálogo pequeno e fechado):**
`related_to` (por omissão) · `mentions` (notícia/menção → pessoa de interesse) · `part_of` (evento/conteúdo → campanha) · `originated_from` (tarefa/conteúdo → origem) · `covers` (notícia → evento).

### 2.3 Módulos

**Calendar — `events`**
title*, description, `type` (lista configurável por equipa em `workspace_options`; por omissão institutional, campaign, publication, ephemeris, deadline), start_at, end_at, all_day, location (texto), organizer (texto), `responsible_user_id`, priority, status (tentative, confirmed, cancelled, done), notes.
*tags, related_entities e reminders vêm do núcleo.*

**Notice Board — `notices`**
title*, body, author_id, published_at, expires_at (null = sem expiração), priority, pinned.

**Pessoas de interesse — `people`**
Perfis de especialistas introduzidos **manualmente**, para responder rapidamente a pedidos dos media ("precisamos de um astrónomo").

name*, academic_title (ex. "Professor Associado"), affiliation (texto: faculdade, centro…), short_bio (1–2 frases, pronta a enviar), bio, expertise_areas (várias, de uma lista partilhada pela equipa), keywords (temas de interesse, separados por vírgulas), career (percurso), cv (ficheiro privado), languages, email, phone, photo (principal) e `person_photos` (galeria), media_notes (experiência com media, disponibilidade), consent_at, last_reviewed_at.

**Dead or Alive** (secção de Pessoas, só para obituários): obituary (preparado com antecedência; `obituary_updated_at` automático) e deceased_on. Quem tem data de falecimento sai da lista de especialistas e deixa de ser sugerido aos media; a secção lista os obituários preparados e os falecidos.

| Tabela de apoio | Campos |
|---|---|
| `expertise_areas` | workspace_id, name, normalized_name (único por workspace). É a lista da dropdown (ex. Astronomia, Saúde Pública…) |
| `person_expertise_area` | person_id, expertise_area_id |

- **Encontrar alguém:** pesquisa por palavra-chave (nome, bio, keywords, afiliação) **ou** escolha de uma área na dropdown. O resultado é uma lista de cartões de perfil.
- **Ação principal:** "Copiar para enviar", que copia o nome, o cargo, a afiliação, a bio curta e os contactos, já formatados para colar num email ao jornalista.
- **Relações úteis:** pedido de imprensa ↔ pessoa (quem foi sugerido), e notícia/menção ↔ pessoa (cobertura em que aparece).
- O `last_reviewed_at` permite assinalar as bios desatualizadas (ex. mais de 12 meses).

**Termos normalizados (áreas de especialidade e tags): proteção contra erro humano no código**
O objetivo é que "Astronomia", "astronomia", " ASTRONOMIA " e "Astronomía" nunca coexistam.
1. **Normalização:** ao gravar, calcula-se o `normalized_name`: trim, espaços colapsados, minúsculas e sem acentos (`Saúde Pública` → `saude publica`). O `name` guarda a forma escrita da primeira vez.
2. **Unicidade na base de dados:** índice único em (`workspace_id`, `normalized_name`). Mesmo que a UI falhe, a base de dados recusa o duplicado.
3. **Reutilizar em vez de criar:** se o termo normalizado já existir, o sistema usa o existente sem erro ("Astronomia já existe; foi associada").
4. **Parecidos:** ao escrever, o campo (combobox com autocompletar) sugere os termos existentes. Se o novo termo for muito parecido com um existente (distância de edição ≤ 2, ex. "Astronomi", "Astrnomia"), pergunta "Queria dizer *Astronomia*?" antes de criar.
5. **Correção:** os managers podem renomear, **fundir** (as associações passam para o termo que fica) e apagar termos.

Com estas proteções, qualquer member pode criar áreas diretamente a partir do perfil.

**News Monitor — `news_items`**
`source_id`, url, canonical_url, url_hash (único), headline*, summary (lead), published_at, retrieved_at, language, `story_id`, raw (json).
- Cada notícia é recolhida **uma só vez** para toda a instância. É conteúdo público, e a partilha é apenas técnica, para evitar recolhas duplicadas.
- **Tudo o que a equipa faz com a notícia é privado ao workspace:** estado, relevância, relações e tarefas. Um workspace só vê as notícias das fontes que subscreve (`news_item_states`).
- Como `news_items` não está em `objects`, as relações com notícias passam pelo `news_item_states` do workspace, que é o objeto relacionável (tipo `news`).

**Online Mentions — `mentions`** (por workspace)
`workspace_id`, `source_id`, url, url_hash, date, excerpt, `rule_id`, matched_keyword, category, relevance, review_status (new, reviewed, relevant, irrelevant, archived), `news_item_id` (opcional).
- Uma menção nasce quando uma **regra de monitorização do workspace** encontra correspondência. Único por `workspace_id` + `url_hash`.
- Se a menção foi encontrada numa notícia já recolhida, aponta para ela (`news_item_id`) e a página não é recolhida outra vez.
- Se a regra estiver associada a uma pessoa de interesse, a menção fica automaticamente relacionada com ela (`mentions`).

| Tabela de apoio | Campos |
|---|---|
| `news_item_states` | id (= objects.id), workspace_id, news_item_id, `status` (new, reviewed, relevant, irrelevant, archived), relevance (low / medium / high + score), reviewed_by, reviewed_at |
| `sources` | name, kind (scraper, rss, google_news), url, config (json), active, poll_interval, last_fetched_at, last_error, consecutive_failures. Catálogo global gerido pelo super admin |
| `workspace_sources` | workspace_id, source_id, is_priority, feeds_into (news, mentions). Cada workspace escolhe as fontes que subscreve |
| `stories` | title, first_seen_at, last_seen_at, item_count (agrupa notícias do mesmo acontecimento) |
| `monitoring_rules` | workspace_id, name, include_terms, exclude_terms, `person_id` (opcional), category, active |

**Fontes iniciais (MVP-2):** JN, DN, Observador, Público, Expresso, SAPO Notícias e Google News.
- **Meios (JN, DN, Observador, Público, Expresso):** usar o RSS de secção se existir (verificar meio a meio na implementação); caso contrário, o scraper genérico.
- **SAPO Notícias:** é um agregador. Os itens apontam para os meios originais, e a deduplicação por URL canónico evita repetições com as outras fontes.
- **Google News:** feeds RSS de pesquisa (`news.google.com/rss/search?q=…&hl=pt-PT&gl=PT`), um por regra de monitorização. Dão cobertura para lá dos seis meios, sobretudo para menções.
  - Os links do Google News são redirecionamentos. É preciso resolver o URL original para deduplicar.
  - Se não for possível resolvê-lo, deduplica-se por título + meio (trigramas).

**Scrapers de media online**
- **Um adaptador genérico configurável** cobre a maioria dos sites: páginas de secção → links de artigos → metadados do artigo (`og:title`, `og:description`, `article:published_time`, JSON-LD `NewsArticle`). Cada meio é só uma configuração (URLs das secções e seletores). Só os sites que fogem ao padrão têm uma classe própria.
- **Usa RSS quando o meio o publica**: é mais estável do que o scraping.
- **Regras:**
  - respeitar o `robots.txt`;
  - User-Agent identificado;
  - no máximo um pedido a cada N segundos por domínio;
  - guardar só título, lead, data e URL, nunca o texto integral (direitos de autor);
  - não contornar paywalls.
- **Só HTML:** sites que dependam de JavaScript para mostrar o conteúdo não são suportados, porque um servidor LAMP não tem browser headless.
- **Os scrapers partem-se quando os sites mudam.** O `consecutive_failures` alimenta o alerta `source_failing`, e o estado de cada fonte aparece na Administração.

**Content Pipeline — `content_items`**
title*, brief/body, format, channel(s), `stage` (idea, preparing, review, approved, scheduled, published, archived), stage_changed_at (para o alerta "parado em Review"), owner_id, due_at, publish_at, published_url.
*Associações a campanhas, eventos e pessoas → relations.* Os estágios são um enum no MVP (❓ tornar configuráveis na Phase 2?).

**Tasks — `tasks`**
title*, description, assigned_to, created_by, deadline, priority, status (todo, in_progress, blocked, done, cancelled), `source_object_id` (origem, opcional).
*`related_entity` → relations; `comments` → núcleo.*

**Press Requests — `press_requests`**
journalist, media_outlet, contact, subject*, request, received_at, deadline, responsible_user_id, status (received, in_progress, awaiting_input, answered, declined, closed), response_notes, answered_at.
- `journalist` e `media_outlet` são texto, com autocompletar a partir dos pedidos anteriores.
- Os especialistas sugeridos ficam como relações com `people`.

**Campaigns — `campaigns`**
name*, description, objectives, audiences (json), start_date, end_date, channels (json), status (planning, active, finished, cancelled). `campaign_users` (responsible_users).
*related_events / related_content → relations `part_of`. assets → anexos na Phase 2.*

**Responsáveis — `record_assignees`** (0.36.0)
Tarefas, conteúdos, eventos e pedidos de imprensa têm várias pessoas responsáveis numa tabela do núcleo (object_id, user_id), com o trait `HasAssignees` (`assignees`, `syncAssignees`, `assignedTo`, `unassigned`). Substitui as colunas `assigned_to`, `owner_id` e `responsible_user_id`. Quem é acrescentado recebe uma notificação (tarefas: "Foi-lhe atribuída uma tarefa"; o resto: "Ficou como responsável"), exceto quem fez a alteração.

**Assets — `assets`** (0.32.0)
A galeria da equipa: imagens, vídeos e gráficos (logótipos, ilustrações). title*, caption (legenda), credit (fotógrafo/autor), category (lista configurável `asset_category` em Definições → Tipos), kind (image, video, graphic, derivado do ficheiro), taken_on, ficheiro no disco privado (`assets/<workspace>/`), thumbnail JPEG de 640 px quando há GD, original_name, mime_type, size, width, height. Tags do núcleo; pesquisa livre pelo índice FTS (título, legenda, crédito, nome do ficheiro) e filtros por tag, categoria e tipo. Ficheiros servidos com CSP `sandbox` (um SVG aberto sozinho não corre scripts) e com suporte a Range para os vídeos. Limite de 100 MB por ficheiro, que o `upload_max_filesize` do servidor também tem de permitir.

**Alerts**
| Tabela | Campos |
|---|---|
| `alert_rules` | workspace_id, rule_type (catálogo fixo), params (json), severity, active, recipients |
| `alerts` | workspace_id, rule_id, object_id, severity, message, dedupe_key, status (open, acknowledged, resolved), created_at, resolved_at |

Catálogo inicial de `rule_type` (cada um com parâmetros configuráveis, sem DSL genérica):
`event_without_owner(hours=48)` · `press_deadline_near(hours=24)` · `content_stuck(stage=review, days=3)` · `campaign_without_content(days_before=7)` · `priority_source_item` · `source_failing(failures=3)` (para o super admin) · `mention_spike(topic, window, factor)` (Phase 2).

**Briefing — `briefings`**
workspace_id, kind (daily | weekly), period_start, period_end, generated_at, content (json, secções com IDs de objetos), ai_summary (opcional).
São *snapshots*: preservam o que se sabia naquele momento.

**AI Assistant — `ai_conversations`, `ai_messages`** (Phase 2)
messages com `cited_object_ids` (json), validados contra `objects` antes de mostrar a resposta.

### 2.4 Mapa de relações (exemplos)

```
                                          part_of
  Pessoa de interesse          Event ──────────────▶ Campaign ◀──── part_of ── Content
    ▲            ▲               ▲
    │ mentions   │ related_to    │ covers
  News/Mention   PressRequest    News ──── originated_from ◀──── Task
```

### 2.5 Escalonamento: várias unidades orgânicas

O piloto é só para a Reitoria, mas a expansão às UOs muda o modelo de dados. Por isso **o modelo nasce multi-workspace** mesmo com um único workspace. Acrescentar `workspace_id` agora custa pouco; acrescentá-lo com dados e código já feitos custa muito.

**Decisão: uma instância com vários workspaces, estanques entre si.**

| | A. Uma instância por UO | B. Uma instância, workspaces estanques (**decidido**) |
|---|---|---|
| Isolamento | Total | Total para os utilizadores, garantido pelo código (scope global + policies + testes) |
| Notícias | Recolhidas N vezes | Recolhidas uma vez; cada workspace faz a sua triagem |
| Visão global | Impossível | Só o super admin (§2.6) |
| Manutenção | N instalações e atualizações | Uma |

**Regra de isolamento:** **só os membros de um workspace veem e acedem ao que é desse workspace.** Não há eventos, campanhas ou quaisquer outros dados visíveis entre equipas. A única exceção é o super admin.

**O que é global (infraestrutura, não dados de equipa):**
- utilizadores (contas) e o catálogo de fontes;
- `news_items` e `stories`: conteúdo público recolhido uma vez;
- catálogos: tipos de relação e tipos de regra de alerta.

**Tudo o resto pertence a um workspace:** events, campaigns, tasks, content, press requests, notices, pessoas de interesse, áreas de especialidade, tags, triagem de notícias, mentions, monitoring rules, alert rules, alerts, briefings e notificações.

**Regras de implementação (obrigatórias desde o MVP-1)**
- **Um único ponto de filtragem no núcleo:** um scope global que aplica o workspace ativo. Pesquisa, Home, briefing, alertas e IA passam todos por ele.
- **Relações só dentro do mesmo workspace**, verificado no serviço de relações.
- **Ingestão:** cada notícia é recolhida uma vez. Para cada workspace que subscreve a fonte é criado um `news_item_state` e são avaliadas as regras de monitorização desse workspace.
- **Isolamento testado:** uma suite de testes que verifica que um workspace não lê nem altera dados de outro, em todos os módulos.
- **No piloto** a UI não mostra o seletor de workspace (só há um), mas o código já o respeita.

### 2.6 Papéis e permissões

Quatro papéis, em hierarquia (cada um inclui as permissões do anterior): member < editor < manager < super admin.
- **Super admin** (global): **pode fazer tudo**, incluindo a visão geral e a configuração de **todos** os workspaces. É um atributo do utilizador (`users.is_super_admin`), não um papel de equipa.
- **Manager** (por workspace): um ou vários por equipa. Gere a equipa e as suas configurações.
- **Editor** (por workspace): um member que também **aprova conteúdos** (Review → Approved). Não gere a equipa nem as configurações.
- **Member** (por workspace): o trabalho do dia a dia.

Uma pessoa pode ter papéis diferentes em equipas diferentes (ex.: manager na FEUP, member na Reitoria), mas em cada momento trabalha num só workspace.

| Ação | Member | Editor | Manager | Super admin |
|---|:-:|:-:|:-:|:-:|
| Ver os dados do seu workspace | ✓ | ✓ | ✓ | ✓ (todos os workspaces) |
| Criar e editar registos da equipa (incluindo os de colegas e perfis de pessoas de interesse) | ✓ | ✓ | ✓ | ✓ |
| Atribuir tarefas a colegas | ✓ | ✓ | ✓ | ✓ |
| Arquivar ou apagar registos de outros | | | ✓ | ✓ |
| **Aprovar conteúdos** (Review → Approved) | | ✓ | ✓ | ✓ |
| Fixar avisos (pinned) | | | ✓ | ✓ |
| Criar áreas de especialidade e tags (com proteção contra duplicados) | ✓ | ✓ | ✓ | ✓ |
| Renomear, fundir e apagar áreas de especialidade e tags | | | ✓ | ✓ |
| **Adicionar utilizadores ao seu workspace**, remover e mudar papéis | | | ✓ (só no seu) | ✓ (em todos) |
| Escolher as fontes do workspace; regras de monitorização e de alerta | | | ✓ | ✓ |
| Receber por omissão os alertas da equipa e as aprovações pendentes | | aprovações | ✓ | opcional |
| Criar, editar e arquivar workspaces | | | | ✓ |
| Gestão global de utilizadores (lista completa, desativar conta, atribuir super admin) | | | | ✓ |
| Catálogo de fontes, catálogos e configuração da instância | | | | ✓ |
| **Visão geral** de todos os workspaces | | | | ✓ |

**Utilizadores: âmbito dos managers**
- Um manager adiciona uma pessoa ao **seu** workspace pelo email institucional. Se a conta ainda não existir, é criada nesse momento, mas **só com esse membership**.
- Remover alguém do workspace apaga só o membership. A conta continua a existir, e as outras equipas da pessoa não são afetadas.
- Um manager **não** vê a lista global de utilizadores, não desativa contas, não adiciona pessoas a outros workspaces e não atribui o papel de super admin.
- **Mínimo de um manager:** não é possível remover nem despromover o último manager de uma equipa (só o super admin o pode substituir).

**Visão geral do super admin**
Uma área própria ("Administração"), fora dos workspaces, com:
- **workspaces:** membros, managers, atividade recente, alertas abertos e aprovações pendentes;
- **ingestão:** estado das fontes (último fetch, erros) e volume de itens;
- **utilizadores:** todos, com os seus memberships;
- **configuração:** catálogo de fontes, catálogos e **email** (ver *Email*, §7).

O super admin também pode entrar em qualquer workspace e ver o conteúdo tal como um manager. Estes acessos a workspaces de que não é membro ficam registados no `activity_log`.

**Outras regras**
- Nas equipas pequenas, a mesma pessoa pode ser manager e fazer o trabalho do dia a dia; o papel só acrescenta permissões.
- O **Home de um manager** acrescenta uma vista de equipa: aprovações pendentes, tarefas em atraso por pessoa e alertas sem dono.
- O **Home de um editor** mostra as aprovações pendentes.
- **Aprovação de conteúdos:** editors, managers e super admin. Quando um conteúdo entra em Review, os editors e managers da equipa são notificados.
- **Não há papel só de leitura** (`viewer`): decidido.
- As permissões vivem nas policies do núcleo e de cada módulo, e são sempre verificadas no backend. Esconder botões na UI não chega.

---

## 3. Arquitetura de navegação

### Estrutura global

```
┌──────────────┬─────────────────────────────────────────────────────────┐
│ CIDASH       │ [ 🔍 Pesquisar tudo…  ⌘K ]      [+ Criar] [🔔 3] [✦ IA] [👤]│
├──────────────┼─────────────────────────────────────────────────────────┤
│ Início       │                                                         │
│ Briefing     │                  área de conteúdo                       │
│              │                                                         │
│ OPERAÇÃO     │                                                         │
│  Calendário  │                                                         │
│  Tarefas     │                                                         │
│  Conteúdos   │                                                         │
│  Campanhas   │                                                         │
│  Imprensa    │                                                         │
│  Avisos      │                                                         │
│  Pessoas     │  (pessoas de interesse)                                 │
│              │                                                         │
│ MONITORIZAÇÃO│                                                         │
│  Notícias    │                                                         │
│  Menções     │                                                         │
│  Alertas     │                                                         │
│ ─────────    │                                                         │
│ Definições   │  (equipa, fontes, regras de monitorização e de alerta,  │
│              │   áreas de especialidade)                               │
│ Administração│  (só super admin)                                       │
└──────────────┴─────────────────────────────────────────────────────────┘
```

### Elementos globais
- **Seletor de workspace** (Phase 3): no topo da sidebar, só para quem pertence a mais de um workspace ou é super admin. Mostra um workspace de cada vez; não existe vista combinada. Escondido no piloto.
- **Administração** (só super admin): a visão geral e a configuração global (§2.6).
- **Pesquisa (⌘K):** paleta de comandos que pesquisa todos os objetos do workspace e também navega e cria.
- **+ Criar:** criação rápida de qualquer tipo.
- **🔔:** alertas e notificações do utilizador.
- **✦ IA** (Phase 2): painel lateral que conhece o contexto do ecrã atual ("resume a cobertura *deste* evento").

### Padrão de ecrã, igual em todos os módulos
1. **Lista/índice:** vista principal do módulo (tabela, Kanban ou calendário), com filtros e vistas guardadas.
2. **Detalhe:** um *drawer* lateral para consulta rápida e uma página completa (URL próprio e partilhável) com:
   - cabeçalho: título, tipo, estado, responsável e ações (**Criar tarefa**, Relacionar, Arquivar);
   - campos do tipo;
   - **painel de relações** agrupado por tipo de objeto;
   - tarefas associadas;
   - comentários e atividade.

### Vistas por módulo
| Módulo | Vistas |
|---|---|
| Calendário | Mês · Semana · Dia · Lista; filtros por tipo, responsável e campanha |
| Tarefas | "Minhas" · Equipa · Lista/Kanban por estado |
| Conteúdos | Kanban (7 estados) · Lista · Calendário editorial (publish_at) |
| Imprensa | Lista ordenada por deadline, com semáforo |
| Pessoas | Caixa de pesquisa + dropdown de área de especialidade → cartões de perfil; ação "Copiar para enviar" |
| Notícias / Menções | Inbox de triagem (novas) · Todas · Agrupadas por story |

---

## 4. Dashboard principal (Início)

Objetivo: responder em 10 segundos a *"o que exige a minha atenção hoje?"*. A ordem segue a urgência.

```
┌──────────────────────────────────────────────────────────────────────────────┐
│ Bom dia, Ana · terça, 23 set                          [Ver briefing de hoje →]│
├──────────────────────────────────────────────────────────────────────────────┤
│ ⚠ ALERTAS (3)  Evento "Dia Aberto" sem responsável · Pedido Público vence 17h │  faixa só
│                Conteúdo "Reitoria/Prémio" há 5 dias em Review          [ver] │  se houver
├──────────┬──────────┬──────────┬──────────┬──────────┬───────────────────────┤
│ Eventos  │ Tarefas  │ Imprensa │ Menções  │ Em Review│  contadores clicáveis │
│ hoje: 4  │ minhas: 7│ <48h: 2  │ novas: 23│ 5        │                       │
├──────────┴──────────┴──────────┴───┬──────┴──────────┴───────────────────────┤
│ HOJE & PRÓXIMOS DIAS               │ AVISOS                                  │
│ 09:30 Receção novos estudantes     │ 📌 Fecho do edifício sexta-feira       │
│ 14:00 Conf. imprensa Reitoria      │    Novo manual de marca disponível     │
│ ── amanhã ──                       ├─────────────────────────────────────────┤
│ 10:00 Assinatura protocolo X       │ REMINDERS & DEADLINES                   │
│ ── próximos 7 dias (6) ──   [+]    │ hoje  Entrega vídeo campanha            │
├────────────────────────────────────┤ qui   Newsletter #34                    │
│ MINHAS TAREFAS          [Equipa ▾] ├─────────────────────────────────────────┤
│ ● Responder a JN (hoje)            │ ÚLTIMAS NOTÍCIAS                        │
│ ● Rever texto prémio (amanhã)      │ Público · U.Porto lidera ranking… (3×)  │
│ ● Fotos evento X (sex)             │ JN · Investigadores do i3S…             │
├────────────────────────────────────┤ [ver todas]                             │
│ PEDIDOS DE IMPRENSA (por deadline) ├─────────────────────────────────────────┤
│ 🔴 Público — ranking — 17:00 hoje  │ NOVAS MENÇÕES (23 por rever)            │
│ 🟠 RTP — entrevista — amanhã       │ por regra: FEUP 8 · Reitor 5 · …        │
├────────────────────────────────────┤ [triar]                                 │
│ CONTEÚDOS EM PREPARAÇÃO            │                                         │
│ Idea 4 · Preparing 6 · Review 5 ·  │                                         │
│ Approved 2 · Scheduled 3           │                                         │
│ ▸ itens parados/atrasados          │                                         │
└────────────────────────────────────┴─────────────────────────────────────────┘
```

**Regras:**
- A coluna esquerda (larga) é "o meu trabalho" e a direita (estreita) é "o que chega".
- Todos os widgets levam à vista filtrada correspondente e permitem **Criar tarefa** diretamente.
- O "(3×)" numa notícia indica que a story tem vários itens: a deduplicação é visível.
- A faixa de alertas só aparece se houver alertas abertos.
- No MVP os widgets são fixos. Widgets personalizáveis por utilizador ficam para *Future*.

---

## 5–6. Âmbito: MVP, Phase 2, Phase 3, Future

**Critério para o MVP:** substituir já as ferramentas dispersas da equipa (folhas de cálculo, emails, calendários) no trabalho **diário**, com o modelo de dados definitivo. A IA fica de fora até os dados existirem.

O MVP divide-se em duas entregas, para pôr a aplicação em uso mais cedo.

### MVP-1 — Núcleo operacional
- **Autenticação com contas locais**, só por convite, com **2FA opcional** que cada utilizador ativa se quiser.
- **Painel de configuração de email** na Administração (servidor SMTP, remetente, email de teste). Enquanto não estiver configurado, os links de convite e reset podem ser copiados à mão.
- Papéis `manager`/`editor`/`member` por equipa + super admin (§2.6), com as permissões nas policies.
- **Workspaces no modelo** (`workspace_id`, roles por workspace, scope global e testes de isolamento), com um só workspace (Reitoria) e sem UI de troca.
- **Núcleo:** objects, relations, tags, comments, activity, reminders (in-app).
- **Calendar:** CRUD; vistas mês, semana, dia e lista; filtros.
- **Notice Board:** avisos com expiração e fixação.
- **Tasks:** CRUD, "criar tarefa a partir de" qualquer objeto, comentários, minhas/equipa.
- **Press Requests:** CRUD, lista por deadline.
- **Content Pipeline:** Kanban com os 7 estados e drag & drop.
- **Campaigns:** CRUD e relações com eventos e conteúdos.
- **Pessoas de interesse:** CRUD, áreas de especialidade com proteção contra duplicados, pesquisa e dropdown, "Copiar para enviar".
- **Search:** pesquisa global full-text (PT, sem acentos, tolerante a erros).
- **i18n:** interface em português com todas as strings traduzíveis desde o início (§7).
- **Home:** dashboard com os widgets acima (menos notícias e menções).

### MVP-2 — Monitorização
- **Sources:**
  - catálogo global com JN, DN, Observador, Público, Expresso, SAPO Notícias e Google News;
  - subscrição por workspace;
  - RSS onde existir, scraper genérico configurável nos restantes.
- **News:** ingestão agendada, deduplicação por URL, stories, inbox de triagem.
- **Mentions:** geradas pelas regras de monitorização (keywords include/exclude, opcionalmente associadas a uma pessoa de interesse), com inbox de revisão.
- **Alerts:** as regras do catálogo (exceto spike), com parâmetros editáveis; notificações in-app.
- **Briefing diário:** determinístico, em página e em arquivo.

### Phase 2
- **AI Assistant** com citações obrigatórias (ver §7)
- Resumo por IA no briefing diário; **briefing semanal** agregado
- Pesquisa semântica (embeddings) e melhor agrupamento em stories
- Alerta `mention_spike`
- Notificações e briefing por email (usa a configuração de email do MVP-1)
- Assets/anexos em campanhas e conteúdos
- Exportação e subscrição ICS do calendário
- Estados e tipos configuráveis (pipeline, tipos de evento). Feito para tipos de evento e formatos de conteúdo (`workspace_options`, Definições → Tipos); os estados dos fluxos (fases do pipeline, estados de imprensa e tarefas) ficam fixos porque aprovações, alertas e briefings dependem deles
- Scoring de relevância mais rico
- Mais fontes: outros meios, e fontes por API se o scraping não chegar

### Phase 3 — Expansão às UOs
Depois da Phase 2 (decidido) e de validado o piloto.
- SSO U.Porto (gerir contas locais de várias UOs não é viável)
- Seletor de workspace
- Administração completa do super admin: criar workspaces, nomear managers, visão geral
- Onboarding por UO: fontes subscritas, regras, áreas de especialidade, guia
- Decisão sobre a base de dados (ver *Notas SQLite*)

### Future
- Directory institucional (unidades, centros, projetos) com sincronização SIGARRA
- Sincronização do calendário com Microsoft 365
- Redes sociais (dependem das APIs e dos custos das plataformas). Feito a partir da 0.31.0 para hashtags: cada equipa segue hashtags (`social_hashtags`, Definições → Monitorização) e `cidash:fetch-social` recolhe publicações públicas no Mastodon (sem conta), Bluesky (conta + app password), YouTube (chave de API) e Instagram (Graph API, conta Business), que passam a menções com `network` e `author`. Credenciais cifradas em Administração → Redes sociais. Facebook, X, TikTok e LinkedIn não têm pesquisa por hashtag numa API gratuita.
- Análise de sentimento e relatórios e analytics de cobertura
- Dashboard personalizável por utilizador
- PWA/mobile e integração com Teams/Slack
- Construtor de regras de alerta genérico

---

## 7. Arquitetura técnica

Stack **PHP**, alojado num **servidor LAMP normal** (com SSH e cron) e **acessível pela web**.

### Stack

| Camada | Escolha | Porquê |
|---|---|---|
| Base de dados | **SQLite 3.45+** | Um só ficheiro, sem servidor. FTS5 (pesquisa, sem acentos, com trigramas), JSON, transações, WAL. Ver *Notas SQLite* abaixo. |
| Backend | **Laravel 13 (PHP 8.3+)**, a partir do starter kit oficial React (Inertia, TypeScript, Tailwind, shadcn/ui, Fortify com 2FA) | Traz de base o que o CIDASH precisa: filas, scheduler, autenticação, policies, migrações, i18n, email, rate limiting, scopes globais e testes. Ver *Porquê Laravel*. |
| Frontend | **Inertia + React + TypeScript** | SPA sem API separada; os componentes ricos (Kanban, calendário, ⌘K) são mais fáceis em React. |
| UI | Tailwind + shadcn/ui (Radix) | Sóbria, acessível, fácil de manter coerente |
| Calendário / Kanban | FullCalendar 6.1 · dnd-kit | Soluções maduras, sem reinventar. A 7.x ainda é recente e os plugins não acompanham |
| Filas / cache | Driver `database` do Laravel (na própria SQLite) | Sem Redis. Ingestão, alertas, briefings e reminders correm em jobs |
| Scraping | Guzzle + Symfony DomCrawler; SimplePie para RSS | Só HTML, sem browser headless |
| i18n | Ficheiros `lang/` do Laravel, partilhados com o React | Ver *Internacionalização* |
| Ambiente local | DDEV (sem serviço de BD) | Já em uso pela equipa |
| Produção | Servidor LAMP (Apache + PHP 8.3+) com cron | Ver *Deploy em LAMP* |
| IA (Phase 2) | Claude API (Anthropic) | ❓ confirmar com os serviços da U.Porto o envio de dados para uma API externa |

### Porquê Laravel

Laravel não é excessivo para o CIDASH. A aplicação tem cerca de 15 módulos, multi-workspace, quatro papéis, jobs agendados (scrapers, alertas, briefings, reminders), i18n e uma superfície pública na web. Com PHP simples ou um micro-framework (Slim, por exemplo), cada uma destas peças teria de ser escrita e mantida à mão: autenticação segura, CSRF, filas, scheduler, migrações, policies, isolamento por workspace e testes. Na prática, seria construir e manter um framework caseiro. O Laravel corre sem dificuldade num LAMP normal.

**O que não usamos**, para manter a simplicidade: Redis, Horizon, Octane, websockets e serviços externos.

### Deploy em LAMP

- **Requisitos do servidor:**
  - PHP 8.3+ com `pdo_sqlite` (SQLite ≥ 3.45 com FTS5), `mbstring`, `intl`, `curl`, `dom`, `fileinfo`;
  - SSH, para correr os comandos `php artisan`;
  - cron.

  Não são precisos Composer nem Node: a `dist/` já traz tudo. SSH, cron e o DocumentRoot configurável estão confirmados; falta verificar as versões.
- **DocumentRoot = `public/`.** O ficheiro SQLite, o `storage/` e o `.env` ficam **fora** da pasta pública.
- **Uma só linha no cron,** a cada minuto: `cd /caminho/cidash && php artisan schedule:run >> /dev/null 2>&1`. O scheduler lança também o processamento da fila (`queue:work --stop-when-empty --max-time=50`) e o backup diário, por isso não é preciso supervisor nem processos permanentes.

**Pacote de deploy: a pasta `dist/`**
- `scripts/build-dist.sh` gera-a a partir do commit atual e recusa correr com alterações por commitar. Leva o código, o `vendor/` de produção e o frontend compilado. O ficheiro `dist/REVISION` identifica o commit.
- **Não leva** `.env`, base de dados nem conteúdo do `storage/`, só as pastas vazias. Pode ser enviada por cima de uma instalação existente sem tocar nos dados.
- **Onde obtê-la:**
  - em cada push para `main`, depois de o CI passar, o GitHub Actions gera-a e publica-a como artefacto `cidash-dist-<commit>` (Actions → execução → *Artifacts*), guardado 30 dias;
  - localmente: `ddev exec scripts/build-dist.sh`.

**Primeira instalação**
1. Enviar o conteúdo da `dist/` para `/caminho/cidash` e apontar o DocumentRoot para `/caminho/cidash/public`.
2. `cp .env.example .env` e editar: `APP_ENV=production`, `APP_DEBUG=false` e `APP_URL=https://…`.
3. `php artisan key:generate`
4. `touch database/database.sqlite && php artisan migrate --force`
5. `php artisan optimize`
6. `php artisan cidash:create-workspace "CI Reitoria" --slug=reitoria`
7. `php artisan cidash:create-user EMAIL NOME --super-admin --workspace=reitoria --role=manager`. O comando imprime o link para definir a palavra-passe, válido 60 minutos.
8. Acrescentar a linha do cron.
9. Garantir que o servidor web pode escrever em `storage/`, `bootstrap/cache/` e `database/`. O SQLite precisa de escrever na pasta para criar os ficheiros `-wal` e `-shm`.

**Atualizações**
1. `php artisan down`
2. Enviar a nova `dist/` por cima. Não apagar `.env`, `database/database.sqlite` nem `storage/`.
3. `php artisan cidash:backup && php artisan migrate --force && php artisan optimize && php artisan up`

Enviar por cima não remove ficheiros apagados entre versões. Se uma versão remover código PHP, apagar a pasta `app/` (ou a que mudou) antes de enviar.

- **Email:** configurado na aplicação, e não no `.env` (ver *Email*).

### Email

- **Painel "Email"** na Administração (só super admin). Campos:
  - servidor SMTP: host, porta, encriptação (TLS/SSL/nenhuma), utilizador e password;
  - remetente: endereço e nome.
- **Enviar email de teste** para o endereço do super admin, com a configuração já guardada. Se falhar, o erro é mostrado e corrige-se a configuração.
- Os valores ficam em `settings`, com a password cifrada (`APP_KEY`). Ao arrancar, o mailer do Laravel é configurado a partir destes valores.
- **Usos:** convites, reset de password, notificações (opcionais por utilizador) e, na Phase 2, o briefing.
- **Sem configuração:** os envios são bloqueados com um aviso na Administração, e os links de convite ou reset podem ser copiados à mão.

### Internacionalização

- A interface é em português (pt-PT). **Nenhuma string fica escrita diretamente no código:** tudo passa pelas funções de tradução.
- **Convenção:** a chave é o texto em inglês (padrão JSON do Laravel). No React escreve-se `t('Log out')` (hook `useTranslation`, em `resources/js/lib/i18n.ts`); no PHP escreve-se `__('Log out')`. A tradução está em `lang/pt_PT.json`, que o Inertia partilha com o React. Uma chave em falta mostra o texto em inglês, pelo que o inglês fica feito automaticamente.
- As mensagens do Laravel (validação, autenticação, passwords) estão em `lang/pt_PT/*.php`, importadas do laravel-lang (pt europeu).
- Datas, números e horas são formatados com `Intl`, segundo o locale.
- `users.locale` guarda a preferência de cada utilizador. Acrescentar o inglês = acrescentar `lang/en/` e o seletor de idioma.
- Só a interface é traduzível. O conteúdo (notícias, tarefas, bios…) fica no idioma em que foi escrito.

### Autenticação

- **Agora:** contas locais (email + password).
  - Sem registo público: as contas só nascem por convite de um manager ou do super admin.
  - O convite é um link para definir a palavra-passe, através do *password broker* `invites`, válido 7 dias. Usa a mesma tabela de tokens da recuperação de palavra-passe (60 minutos), mas tem página própria (`/invitation/{token}`).
  - O link é enviado por email quando o email está configurado. Se não estiver, ou se o envio falhar, é mostrado ao manager para ser copiado.
  - `activated_at` marca a aceitação do convite. Uma recuperação de palavra-passe também ativa a conta.
  - **2FA (TOTP) opcional para todos os utilizadores:** cada um ativa ou desativa no seu perfil, com uma app de autenticação e códigos de recuperação. Ninguém é obrigado.
  - **Telemóvel perdido:** o super admin pode repor o 2FA de um utilizador, e a ação fica registada no `activity_log`.
  - Implementação: Laravel Fortify (2FA, reset de password, confirmação de password).
  - Rate limiting e bloqueio temporário após tentativas falhadas.
  - Passwords com requisitos mínimos de tamanho e verificação contra passwords comprometidas conhecidas (regra `uncompromised` do Laravel).
- **Futuro:** SSO U.Porto. O email institucional é a chave que liga cada conta à identidade SSO, por isso a migração não obriga a recriar contas. A autenticação fica isolada num serviço, para o SSO entrar como um segundo método.

### Notas SQLite

**Motivo da escolha: mobilidade.** Uma instância completa do CIDASH é a pasta da aplicação mais uma pasta de dados (`database.sqlite` e `storage/` com os anexos). Mudar de servidor ou montar uma cópia local é transferir essa pasta, sem servidor de base de dados nem Redis para instalar. Para manter isto verdadeiro, todo o estado persistente (dados, filas, cache, sessões, uploads) tem de viver nessa pasta de dados.

- **Fuso horário:** a aplicação usa `Europe/Lisbon`, e as datas ficam guardadas em hora local. Horas vindas do browser em UTC são convertidas antes de gravar.
- **Configuração obrigatória** em cada ligação (`config/database.php`): `journal_mode=WAL`, `busy_timeout=5000`, `foreign_keys=ON`, `synchronous=NORMAL`.
- **Transações `IMMEDIATE`:** o write lock é pedido logo no `BEGIN`, e assim o `busy_timeout` aplica-se. Com o valor por omissão (`DEFERRED`), uma transação que começa a ler e depois escreve falha de imediato com `database is locked`.
- **Concorrência:** um processo escreve de cada vez (as leituras não bloqueiam). Chega para os ~20 utilizadores do piloto e os workers. Transações curtas; nada de trabalho HTTP dentro de uma transação.
- **Backups e mudanças de servidor:** `VACUUM INTO` ou `sqlite3 .backup` agendado. Para mover a instância, parar os workers ou usar `VACUUM INTO`. Nunca copiar o ficheiro com a aplicação a escrever.
- **UUIDs** guardados como `TEXT`, e arrays como colunas JSON.
- **Portabilidade:** usar só o query builder/Eloquent e evitar SQL específico fora de `Core/Search`, para que a migração para MariaDB/MySQL (já incluído no LAMP) ou PostgreSQL seja possível sem reescrever os módulos.
- **Quando migrar:** esperas frequentes por escrita (`database is locked`), vários servidores de aplicação, ou pesquisa semântica que o SQLite não sirva.
- **Expansão às UOs = ponto de decisão.** Com várias UOs, um só ficheiro é um ponto único de falha para todas, e mais utilizadores e workers aumentam a contenção de escrita. Por isso:
  - **Piloto:** SQLite.
  - **Antes de abrir a outras UOs:** reavaliar com dados reais (volume, `database is locked`, tempos de resposta). Se for preciso, migrar para MariaDB/MySQL.
  - **Para a migração ser barata:** Eloquent em todo o lado, pesquisa atrás de uma interface (`Core/Search`), sem SQL específico nos módulos e um teste de migração de dados preparado.

### Organização do código (modular)

```
app/
  Core/            workspaces (scope + policies), objects, relations, tags, comments, activity, reminders, search
  Modules/
    Calendar/      Models, Http, Policies, Jobs, …
    Notices/
    People/        (pessoas de interesse, áreas de especialidade)
    Sources/       (catálogo, subscrições, scrapers, RSS, Google News, fetch e normalização)
    News/          (news_items, estados por workspace, stories)
    Mentions/      (mentions, monitoring rules)
    Content/
    Tasks/
    Press/
    Campaigns/
    Alerts/        (rule_types como classes: um ficheiro por regra)
    Briefing/
    Admin/         (área do super admin: workspaces, utilizadores, fontes, settings/email)
    Assistant/     (Phase 2)
resources/js/
  core/            layout, ⌘K, RelationsPanel, ObjectDrawer, CreateTaskAction
  modules/<nome>/
lang/pt_PT.json    (strings da UI; chave = texto em inglês)
lang/pt_PT/        (mensagens do Laravel)
scripts/build-dist.sh
```

Cada módulo regista o seu `type` no núcleo (label, ícone, rota, campos pesquisáveis e renderer de resumo). É isto que permite ao Search, ao painel de relações, ao Home e à IA tratarem qualquer tipo sem conhecerem o módulo.

### Processos em background

| Job | Frequência |
|---|---|
| `FetchSource` (por fonte) | poll_interval (ex. 15 min) |
| `ProcessNewsItem` (normalizar, dedup, story, estados por workspace) | por item |
| `MatchMonitoringRules` (cria mentions por workspace) | por item |
| `EvaluateAlertRules` (por workspace) | 5–15 min, e também em eventos de domínio |
| `SendReminders` | 1 min |
| `GenerateDailyBriefing` (por workspace) | 07:00 dias úteis |
| `ExpireNotices` | diário |
| `BackupDatabase` | diário |

### Pesquisa
- **Índice:** tabela virtual FTS5 `objects_fts` com o tokenizer `unicode61 remove_diacritics 2` (ignora acentos), sempre filtrada pelo workspace ativo. A camada de serviço atualiza-a a partir dos campos que cada tipo declara.
- **Ranking:** por `bm25`.
- **Palavras incompletas:** cada palavra é pesquisada como prefixo ("invest" encontra "investigação"). Não há tolerância a erros de escrita; a tabela de trigramas prevista foi abandonada, porque dá pesquisa por partes de palavras e não correção de gralhas.
- **Implementação:** `App\Core\Search\Search` (interface) com `SqliteSearch` (tabela `objects_fts`). Cada modelo declara `$searchable`, e o `IsRecord` mantém o índice atualizado. `cidash:search-reindex` reconstrói o índice (por exemplo, depois de importar dados). A tabela FTS não passa pelo filtro do Eloquent, por isso a implementação filtra explicitamente pelo workspace.
- **Limitação:** o FTS5 não reconhece variações de palavras em português ("notícia"/"notícias"). Usa-se pesquisa por prefixo.

### AI Assistant (Phase 2): desenho
1. **Tools estruturadas** (tool use) para perguntas factuais: `list_events(range)`, `press_requests(status)`, `events_without_owner()`, `news(query, since)`, `mentions(status)`, `find_people(topic)`… A resposta vem de SQL e não de "memória" do modelo.
2. **Recuperação:** primeiro com FTS5, tags e áreas de especialidade ("tudo sobre sustentabilidade"). Pesquisa semântica só se for necessária. Num LAMP é incerto conseguir carregar a extensão `sqlite-vec`; a alternativa é guardar os embeddings numa tabela e calcular a similaridade em PHP, viável para o volume do piloto.
3. **Citações obrigatórias:** cada registo entregue ao modelo leva o seu `object_id`. A resposta tem de os referenciar, e o backend valida que cada citação existe e foi de facto fornecida no contexto. As que não cumprem são descartadas ou assinaladas.
4. **Âmbito restrito:** o system prompt proíbe conhecimento externo. Se os dados não chegarem, a IA diz que não sabe.
5. As permissões e o scope de workspace do utilizador aplicam-se às tools, e todas as conversas ficam registadas.

### Segurança e dados
- **Aplicação exposta na web:**
  - HTTPS obrigatório, com HSTS;
  - sem registo público;
  - rate limiting no login;
  - 2FA disponível para quem o quiser ativar;
  - credenciais de email cifradas na base de dados;
  - cabeçalhos de segurança (CSP, X-Frame-Options…);
  - `.env`, SQLite e `storage/` fora da pasta pública;
  - `APP_DEBUG=false`;
  - dependências atualizadas (`composer audit` em CI);
  - backups copiados para fora do servidor.
- **Isolamento entre workspaces:** o scope de workspace é a fronteira de segurança principal. Qualquer query que o contorne (jobs de ingestão, Administração, IA) tem de o fazer explicitamente e ser revista.
- **Dados de pessoas:**
  - Contactos de jornalistas: profissionais, com base legal simples (interesse legítimo). Basta poder corrigir ou apagar um contacto a pedido.
  - Pessoas de interesse: perfis criados com o conhecimento da pessoa (`consent_at`) e revistos periodicamente (`last_reviewed_at`).
- `activity_log` como trilho de auditoria.
