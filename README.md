# Reino Class - Presença

Plugin privado do Reino Class para controlar a presença nas aulas presenciais integradas ao Tutor LMS.

## Versão 0.1.0

- Criação de aulas e vínculo opcional com cursos do Tutor LMS.
- Abertura de chamada por período configurável.
- Confirmação autenticada por código de seis dígitos ou QR Code.
- Bloqueio de duplicidade por aluno e aula.
- Encerramento automático ao fim da janela.
- Relatório por aula e exportação CSV restrita a administradores.
- Página de confirmação criada automaticamente.

## Privacidade e segurança

- Apenas usuários autenticados podem confirmar presença.
- Códigos e tokens são armazenados como hashes.
- IP e agente do navegador não são armazenados em texto; apenas hashes são mantidos para futura auditoria antifraude.
- Formulários administrativos e públicos usam nonces do WordPress.
- Dados de presença são preservados se o plugin for desinstalado acidentalmente.

## Desenvolvimento

O plugin requer WordPress 6.4 ou superior, PHP 8.0 ou superior e foi projetado para funcionar ao lado do Tutor LMS.
