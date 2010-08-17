<?php

$namespaceNames = array();

// For wikis without LiquidThreads installed.
if ( ! defined('NS_LQT_THREAD') ) {
	define( 'NS_LQT_THREAD', 90 );
	define( 'NS_LQT_THREAD_TALK', 91 );
	define( 'NS_LQT_SUMMARY', 92 );
	define( 'NS_LQT_SUMMARY_TALK', 93 );
}

$namespaceNames['en'] = array(
	NS_LQT_THREAD       => 'Thread',
	NS_LQT_THREAD_TALK  => 'Thread_talk',
	NS_LQT_SUMMARY      => 'Summary',
	NS_LQT_SUMMARY_TALK => 'Summary_talk',
);

$namespaceNames['fi'] = array(
	NS_LQT_THREAD       => 'Viestiketju',
	NS_LQT_THREAD_TALK  => 'Keskustelu_viestiketjusta',
	NS_LQT_SUMMARY      => 'Yhteenveto',
	NS_LQT_SUMMARY_TALK => 'Keskustelu_yhteenvedosta',
);

$namespaceNames['pt'] = array(
	NS_LQT_THREAD => 'Tópico',
	NS_LQT_THREAD_TALK => 'Tópico_discussão',
	NS_LQT_SUMMARY => 'Resumo',
	NS_LQT_SUMMARY_TALK => 'Resumo_discussão',
);
