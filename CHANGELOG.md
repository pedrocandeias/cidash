# Changelog

Versões em [SemVer](https://semver.org/lang/pt-PT/): enquanto o projeto estiver em 0.x, `feat` sobe a versão *minor* e `fix` sobe a *patch*. As entradas seguem o estilo Conventional Commits.

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
