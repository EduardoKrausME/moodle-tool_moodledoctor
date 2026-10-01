<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Brazilian Portuguese strings.
 *
 * @package    tool_moodledoctor
 * @copyright  2026 Eduardo Kraus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$string['additionalcontext'] = 'Contexto adicional';
$string['additionalcontext_help'] = 'Contexto operacional opcional. Não cole segredos intencionalmente; o sanitizer é defesa em profundidade, não autorização para compartilhar credenciais.';
$string['aifailed'] = 'Falha no diagnóstico por IA: {$a}';
$string['aiheading'] = 'Interpretação da IA';
$string['ainotfact'] = 'A seção abaixo contém hipóteses e recomendações geradas por IA. Trate-a como interpretação, não como fato coletado.';
$string['cancelpreview'] = 'Cancelar';
$string['checklistquestion'] = 'Objetivo da investigação';
$string['checklistquestion_help'] = 'Opcional. Exemplo: “Respostas 504 intermitentes após restauração de curso”.';
$string['checkliststatus'] = 'Contexto do checklist';
$string['confirmsend'] = 'Enviar este payload para IA';
$string['cronstatus'] = 'Status do cron e tasks';
$string['dangerousactionsdisabled'] = 'O Moodle Doctor é somente leitura. Ele não executa shell, SQL gerado por IA, alterações de configuração, purge de cache, upgrade de plugin, execução de task nem qualquer correção automática.';
$string['environmentstatus'] = 'Status do ambiente';
$string['errorinput'] = 'Mensagem de erro ou stack trace';
$string['errorinput_help'] = 'Cole o erro ou stack trace. O Moodle Doctor remove segredos antes de criar a prévia para IA.';
$string['factsexplanation'] = 'Estes dados são coletados de forma determinística pelo Moodle Doctor e ficam separados de qualquer interpretação da IA.';
$string['factsheading'] = 'Fatos coletados';
$string['generatechecklist'] = 'Preparar prévia do checklist';
$string['healthsummary'] = 'Resumo de saúde do sistema';
$string['invalidpreview'] = 'A prévia não existe, expirou, já foi usada ou pertence a outra sessão.';
$string['manualtoolarge'] = 'O texto informado é muito grande. Limite cada campo a {$a} caracteres.';
$string['moodledoctor:use'] = 'Usar o Moodle Doctor';
$string['noinstalledplugins'] = 'Nenhum plugin instalado foi encontrado.';
$string['nopermission'] = 'Você não tem permissão para usar o Moodle Doctor.';
$string['plugincomponent'] = 'Plugin';
$string['plugincontext'] = 'Problema observado';
$string['plugincontext_help'] = 'Sintomas ou texto de erro opcional relacionado ao plugin selecionado.';
$string['pluginname'] = 'Moodle Doctor';
$string['pluginstatus'] = 'Status do plugin';
$string['prepareai'] = 'Preparar diagnóstico com IA';
$string['previewexplanation'] = 'Este é exatamente o array de mensagens que o Moodle Doctor passará ao local_ai_bridge. O bridge pode acrescentar a system instruction configurada para o purpose moodledoctor-diagnose.';
$string['previewheading'] = 'Prévia do payload para IA';
$string['previewmanual'] = 'Sanitizar e visualizar';
$string['privacy:metadata:external'] = 'Quando um administrador confirma explicitamente um diagnóstico com IA, dados de diagnóstico sanitizados são roteados pelo local_ai_bridge para o provedor de IA configurado no site.';
$string['privacy:metadata:external:diagnosticfacts'] = 'Fatos determinísticos do Moodle selecionados para o diagnóstico solicitado.';
$string['privacy:metadata:external:manualcontext'] = 'Texto de erro, stack trace ou contexto de investigação sanitizado e informado manualmente pelo administrador.';
$string['sanitizednotice'] = 'Todo conteúdo enviado à IA passa pelo sanitizer. Credenciais, tokens, cookies, identificadores de sessão, cabeçalhos Authorization, DSNs e credenciais embutidas em URLs conhecidas são removidos.';
$string['tab:checklist'] = 'Checklist de investigação';
$string['tab:cron'] = 'Diagnosticar cron';
$string['tab:environment'] = 'Diagnosticar ambiente';
$string['tab:error'] = 'Explicar erro';
$string['tab:health'] = 'Health check';
$string['tab:plugin'] = 'Diagnosticar plugin';
$string['unknownplugin'] = 'Componente de plugin desconhecido.';
