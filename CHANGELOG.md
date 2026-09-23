# Changelog

Versões em [SemVer](https://semver.org/lang/pt-PT/): enquanto o projeto estiver em 0.x, `feat` sobe a versão *minor* e `fix` sobe a *patch*. As entradas seguem o estilo Conventional Commits.

## 0.17.0 (2026-09-23)

- feat: regras de monitorização por equipa (Definições → Monitorização, só managers): termos a incluir e a excluir, pessoa de interesse opcional, categoria e pausa
- feat: correspondência por palavras inteiras, sem distinguir maiúsculas nem acentos; uma regra com pessoa de interesse procura também o nome dela
- feat: Menções: notícias que correspondem a uma regra, com triagem (por triar, relevantes, não relevantes), relevância, comentários, relações e "Criar tarefa"
- feat: menções de uma pessoa de interesse ficam ligadas ao perfil dela
- feat: cada regra pode fazer a sua própria pesquisa no Google News (a cada 30 minutos), só para a equipa dela
- feat: subscrições com "só as que correspondem às regras", ligado por omissão, para as Notícias não se encherem de notícias gerais; uma equipa sem regras continua a receber tudo

## 0.16.0 (2026-09-23)

- feat: catálogo global de fontes (Administração → Fontes): RSS/Atom, pesquisas do Google News e recolhedor HTML genérico, com estado, erros e "Recolher agora"
- feat: subscrição de fontes por equipa, com fontes prioritárias (Definições → Fontes)
- feat: recolha agendada a cada 5 minutos (`cidash:fetch-sources`); cada notícia é guardada uma só vez, só com metadados
- feat: endereços canónicos (sem parâmetros de tracking) para não repetir notícias
- feat: agrupamento em histórias por semelhança de títulos, incluindo o mesmo artigo vindo de meios diferentes
- feat: caixa de Notícias por triar, agrupada por história; marcar uma história como relevante aplica-se a todas as notícias dela
- feat: página da notícia com ligação ao original, a mesma história noutros meios, comentários, relações e "Criar tarefa"
- feat: catálogo inicial (`cidash:install-default-sources`): JN, DN, Observador, Público, SAPO Notícias e Google News; o Expresso bloqueia pedidos automáticos e chega via Google News
- feat: o recolhedor HTML respeita o `robots.txt`, espera entre pedidos ao mesmo domínio e identifica-se

## 0.15.0 (2026-09-23)

- feat: Calendário com filtros por tipo, responsável e campanha
- feat: arrastar e redimensionar eventos no calendário para mudar a data e a duração
- feat: Tarefas em vista de quadro (Kanban por estado), além da lista
- feat: Conteúdos em vista de lista e calendário editorial pela data de publicação, além do quadro
- refactor: componente Kanban genérico, partilhado por tarefas e conteúdos
- fix: horas enviadas com fuso horário ao editar eventos são convertidas para o fuso da aplicação

## 0.14.0 (2026-09-23)

- feat: marca provisória do CIDASH (logótipo, favicon e ícone) em vez do logótipo do Laravel
- chore: dados de demonstração fictícios (`DemoSeeder`) para a CI Reitoria, carregados com `migrate:fresh --seed` em ambiente local
- fix: o seeder deixa de desligar os eventos dos modelos, de que os registos dependem

## 0.13.0 (2026-09-23)

- feat: Administração → Utilizadores (super admin): todas as contas com equipas, papéis e estado
- feat: desativar e reativar contas; uma conta desativada não entra e perde a sessão aberta
- feat: atribuir e retirar super admin (nunca ao próprio)
- feat: repor o 2FA de quem perdeu o telemóvel
- feat: acrescentar contas a equipas, mudar papel e retirar, mantendo sempre um manager
- feat: ações globais registadas no histórico sem equipa associada
- test: teste da lista de membros deixa de depender da ordem de nomes aleatórios

## 0.12.0 (2026-09-23)

- feat: página inicial com saudação, contadores (eventos hoje, tarefas abertas, imprensa com prazo < 48 h, conteúdos em revisão) e widgets
- feat: widgets de próximos 7 dias, as minhas tarefas, pedidos de imprensa por prazo, conteúdos em preparação (e parados em revisão), avisos e os meus lembretes
- feat: editores e managers veem as aprovações pendentes; managers veem as tarefas em atraso por pessoa

## 0.11.0 (2026-09-23)

- feat: pesquisa global em todos os registos (tarefas, eventos, avisos, pedidos de imprensa, conteúdos, campanhas, pessoas), sem distinção de acentos nem maiúsculas e com palavras incompletas
- feat: paleta de pesquisa e navegação com Ctrl+K / ⌘K, com excertos e navegação por teclado
- feat: o painel de relações e a secção de campanha passam a usar a mesma pesquisa
- feat: comando `cidash:search-reindex` para reconstruir o índice

## 0.10.0 (2026-09-23)

- feat: Pessoas de interesse: perfis de especialistas com cargo, afiliação, bio curta e longa, palavras-chave, línguas, contactos, fotografia e notas sobre media
- feat: áreas de especialidade sem duplicados, com sugestões e "Queria dizer…?"
- feat: pesquisa por palavra-chave e filtro por área
- feat: "Copiar para enviar" com o perfil formatado para um email ao jornalista
- feat: bios não revistas há mais de um ano assinaladas, com "Marcar como revista hoje"
- feat: fotografias guardadas fora da pasta pública e servidas só à equipa
- feat: Definições → Termos (managers): renomear, fundir e apagar tags e áreas de especialidade
- refactor: vocabulários (tags e áreas) partilham a mesma base (`Vocabulary`)

## 0.9.0 (2026-09-23)

- feat: Campanhas com descrição, objetivos, públicos, datas, canais, estado e vários responsáveis
- feat: secção "Nesta campanha" para acrescentar eventos e conteúdos (relação `part_of`) com pesquisa por título
- feat: lista de campanhas planeadas e em curso, com o número de itens de cada uma
- refactor: pesquisa de registos extraída para um componente reutilizável (`RecordSearch`)

## 0.8.0 (2026-09-23)

- feat: Pipeline de conteúdos em Kanban com as fases Ideia, Em preparação, Revisão, Aprovado, Agendado, Publicado e Arquivado
- feat: arrastar e largar entre fases; aprovar (ou saltar a revisão) é exclusivo de editores e managers, verificado no servidor
- feat: editores e managers recebem uma notificação quando um conteúdo entra em Revisão
- feat: conteúdos com formato, canais, responsável, prazo, data de publicação e endereço publicado
- feat: indicação de conteúdos parados em Revisão há 3 dias ou mais
- feat: página do conteúdo com relações, comentários, histórico e "Criar tarefa"

## 0.7.0 (2026-09-23)

- feat: Pedidos de Imprensa com jornalista, meio, contacto, pedido, receção, prazo, responsável, estado e notas da resposta
- feat: lista ordenada por prazo com semáforo (vermelho < 24 h, âmbar < 72 h) e filtros em aberto, terminados e tudo
- feat: autocompletar de jornalistas e meios já usados
- feat: data da resposta registada ao marcar como respondido
- feat: página do pedido com comentários, relações, lembretes (a partir do prazo), histórico e "Criar tarefa"

## 0.6.0 (2026-09-23)

- feat: Avisos internos com texto, prioridade, publicação agendada e expiração
- feat: avisos fixados (só managers) aparecem primeiro e destacados
- feat: vistas "Em vigor" e "Agendados e expirados"
- feat: página do aviso com comentários, relações e histórico

## 0.5.0 (2026-09-23)

- feat: Calendário com vistas mês, semana, dia e agenda (FullCalendar), cores por tipo de evento
- feat: eventos com tipo, início e fim (ou dia inteiro), local, organizador, responsável, prioridade, estado, notas e tags
- feat: lembretes pessoais em qualquer registo, entregues como notificação in-app (`cidash:send-reminders`, a cada minuto)
- feat: tags com sugestões e aviso "Queria dizer…?" para termos parecidos
- feat: painel de relações para ligar registos, com pesquisa por título
- feat: "Criar tarefa" a partir de um evento; a tarefa fica ligada à origem
- feat: a página de uma tarefa mostra as suas relações
- chore: fuso horário da aplicação passa a Europe/Lisbon

## 0.4.0 (2026-09-23)

- feat: módulo de Tarefas com vistas "Minhas" e "Equipa", filtros (abertas, concluídas, todas) e conclusão rápida
- feat: página de tarefa com edição, comentários e histórico; só o criador ou um manager pode apagar
- feat: tarefas podem ter um registo de origem (relação `originated_from`)
- feat: comentários genéricos em qualquer registo
- feat: notificações in-app com sino na barra de topo; notificação quando uma tarefa é atribuída
- fix: o workspace é resolvido antes do *route model binding*, para os modelos filtrados por equipa

## 0.3.0 (2026-09-23)

- feat: núcleo de dados: `objects` (`Record`), relações, tags, comentários e histórico de atividade
- feat: trait `IsRecord`: cada registo de domínio cria o seu `Record`, sincroniza o título e regista alterações
- feat: filtro de workspace que falha sem workspace ativo, em vez de expor dados de outras equipas
- feat: tags sem duplicados (maiúsculas, acentos e espaços) e sugestão de termos parecidos
- feat: registo dos acessos do super admin a equipas de que não é membro

## 0.2.0 (2026-09-23)

- feat: Administração → Email: configuração SMTP guardada na aplicação (palavra-passe cifrada) e email de teste
- feat: convites válidos 7 dias, enviados por email ou mostrados para copiar; página "Aceitar convite"
- feat: Definições → Equipa: adicionar membros, mudar papel, remover e reenviar convite
- feat: a equipa mantém sempre pelo menos um manager; o único manager não pode eliminar a conta
- feat: `cidash:create-user` envia o mesmo convite

## 0.1.0 (2026-09-23)

- feat: base Laravel 13 + React (starter kit), SQLite em modo WAL, filas e cache na base de dados
- feat: interface em português europeu, preparada para tradução
- feat: sem registo público nem verificação de email; passkeys desligadas; 2FA opcional
- feat: workspaces com papéis member, editor e manager, e super admin
- feat: comandos `cidash:create-workspace`, `cidash:create-user` e `cidash:backup` (backup diário)
- build: pacote de deploy `dist/`, gerado também pelo CI como artefacto
- ci: testes, lint, análise estática e `composer audit` no GitHub Actions
- docs: `ARCHITECTURE.md`, `TODO.md` e `CLAUDE.md`
