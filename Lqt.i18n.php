<?php
/**
 * Internationalisation file for Liquid Threads extension.
 *
 * @package MediaWiki
 * @addtogroup Extensions
 * @subpackage LiquidThreads
 * @author David McCabe <davemccabe@gmail.com> / I18n file by Erik Moeller and Siebrand Mazeland
 * @licence GPL2
*/

$messages = array();

$messages['en'] = array(
	'lqt-desc' => 'Add threading discussions to talk pages',
	'lqt-nothread' => 'There are no threads in the archive.',
	'lqt_newmessages' => 'New messages',
	'lqt_movethread' => 'Move thread to another page',
	'lqt_deletethread' => 'Delete or undelete thread',
	'lqt_browse_archive_without_recent' => 'View archived threads',
	'lqt_browse_archive_with_recent' => 'older',
	'lqt_recently_archived' => 'Recently archived:',
	'lqt_contents_title' => 'Contents:',
	'lqt_add_header' => 'Add header',
	'lqt_new_thread' => 'Start a new discussion',
	'lqt_in_response_to' => 'In reply to $1 by $2, above:',
	'lqt_edited_notice' => 	'Edited',
	'lqt_move_placeholder' => "''Placeholder left when the thread was moved to another page.''",
	'lqt_reply' => 'Reply',
	'lqt_delete' => 'Delete',
	'lqt_undelete' => 'Undelete',
	'lqt_permalink' => 'Permalink',
	'lqt_fragment' => 'a fragment of a $1 from $2',
	'lqt_discussion_link' => 'discussion', // substituted above
	'lqt_from_talk' => 'from $1',
	'lqt_newer' => '«newer',
	'lqt_older' => 'older»',
	'lqt_hist_comment_edited' => 'Comment text edited',
	'lqt_hist_summary_changed' => 'Summary changed',
	'lqt_hist_reply_created' => 'New reply created',
	'lqt_hist_thread_created' => 'New thread created',
	'lqt_hist_deleted' => 'Deleted',
	'lqt_hist_undeleted' => 'Undeleted',
	'lqt_hist_moved_talkpage' => 'Moved',
	'lqt_hist_listing_subtitle' => 'Viewing a history listing.',
	'lqt_hist_view_whole_thread' => 'View history for the entire thread',
	'lqt_hist_no_revisions_error' => 'This thread does not have any history revisions. That is pretty weird.',
	'lqt_hist_past_last_page_error' => 'You are beyond the number of pages of history that exist.',
	'lqt_hist_tooltip_newer_disabled' => 'This link is disabled because you are on the first page.',
	'lqt_hist_tooltip_older_disabled' => 'This link is disabled because you are on the last page.',
	'lqt_revision_as_of' => "Revision as of $1.",
	'lqt_change_new_thread' => 'This is the thread\'s initial revision.',
	'lqt_change_reply_created' => 'The highlighted comment was created in this revision.',
	'lqt_change_edited_root' => 'The highlighted comment was edited in this revision.',
	'lqt_youhavenewmessages' => 'You have [$1 new messages].',
	'lqt_changes_from' => ' from ',
	'lqt_changes_summary_of' => ' of ',
	'lqt_protectedfromreply' => 'This thread has been $1 from being replied to.',
	'lqt_protectedfromreply_link' => 'protected',
	'lqt_subject' => 'Subject: ',
	'lqt_nosubject' => '«no subject»',
	'lqt_noreason' => 'No reason given.',
	'lqt_move_placeholder' => 'This thread is a placeholder indicating that a thread, $1, was removed from this page to another talk page.
This move was made by $2 at $3.',
	'lqt_thread_deleted_for_sysops' => 'This thread has been $1 and is only visible to sysops.',
	'lqt_thread_deleted_for_sysops_deleted' => 'deleted', // substituted above in bold
	'lqt_thread_deleted' => 'This thread has been deleted.',
	'lqt_summary_notice' => 'There have been no changes to this discussion for at least $2 days.
If it is concluded, you may want to $1.',
	'lqt_summary_notice_link' => 'write a summary',
	'lqt_summary_label' => 'This thread has been summarized as follows:',
	'lqt_summary_subtitle' => 'the summary of $1.',
	'lqt_nosuchrevision' => 'There is no such revision of this thread.',
	'lqt_nosuchthread' => 'There is no such thread.',
	'lqt_threadrequired' => 'You must specify a thread in the URL.',
	'lqt_move_movingthread' => 'Moving $1. This thread is part of $2.',
	'lqt_move_torename' => 'To rename this thread, $1 and change the \'Subject\' field.',
	'lqt_move_torename_edit' => 'edit it', // substituted above as a link
	'lqt_move_destinationtitle' => 'Title of destination talkpage:',
	'lqt_move_move' => 'Move',
	'lqt_move_nodestination' => 'You must specify a destination.',
	'lqt_move_noreason' => 'No reason given.',
	'lqt_move_success' => 'The thread was moved to $1.',
	'lqt_delete_undeleting' => 'Undeleting $1.',
	'lqt_delete_undeletethread' => 'Undelete thread',
	'lqt_delete_partof' => 'This thread is part of $1.',
	'lqt_delete_deleting' => 'Deleting $1 and $2 to it.',
	'lqt_delete_deleting_allreplies' => 'all replies', // subst above in bold
	'lqt_delete_deletethread' => 'Delete thread and replies',
	'lqt_delete_deleted' => 'The thread was deleted.',
	'lqt_delete_undeleted' => 'The thread was undeleted.',
	'lqt_delete_return' => 'Return to $1.',
	'lqt_delete_return_link' => 'the talkpage', // subst above
	'lqt_delete_unallowed' => 'You are not allowed to delete threads.',
	'lqt_talkpage_autocreate_summary' => 'Talkpage autocreated when first thread was posted.',
	'lqt_header_warning_before_big' => '',
	'lqt_header_warning_big' => 'You are editing a $1. ',
	'lqt_header_warning_after_big' => 'Headers are for announcements and prefaces.
You might instead want to $2.',
	'lqt_header_warning_bold' => 'discussion page header',
	'lqt_header_warning_new_discussion' => 'start a new discussion',
	'lqt_sorting_order' => 'Sorting order:',
	'lqt_remember_sort' => 'Remember this preference',
	'lqt_sort_newest_changes' => 'last modified first',
	'lqt_sort_newest_threads' => 'newest threads first',
	'lqt_sort_oldest_threads' => 'oldest threads first',
	'lqt-any-date'            => 'Any date',
	'lqt-only-date'           => 'Only these dates:',
	'lqt-date-from'           => 'From',
	'lqt-date-to'             => 'To',
	'lqt-title'               => 'Title',
	'lqt-summary'             => 'Summary',
	'lqt-older'               => 'older',
	'lqt-newer'               => 'newer',
	'lqt-searching'           => 'Searching for threads',
	'lqt-read-message'        => 'Read',
	'lqt-read-message-tooltip'=> 'Remove this thread from new messages. It will still be visible on its original talk page.',
	'lqt-read-all'            => 'Mark all as read',
	'lqt-read-all-tooltip'    => 'Remove all threads from new messages. They will still be visible on their original talk pages. This operation is undoable.',
	'lqt-marked-read'         => 'Thread \'\'\'$1\'\'\' marked as read.',
	'lqt-count-marked-read'   => '$1 {{PLURAL:$1|message|messages}} marked as read.',
	'lqt-email-undo'          => 'Undo',
	'lqt-messages-sent'       => 'Messages sent to you:',
	'lqt-other-messages'      => 'Messages on other talk pages:',
	'lqt-new-messages'        => 'There are new messages for you.',
	'lqt-email-info-undo'     => 'Bring back the thread you just dismissed.',
	'lqt-date-info'           => 'This link is disabled because you are viewing threads from all dates.',
);

/** Message documentation (Message documentation)
 * @author Helix84
 * @author Jon Harald Søby
 * @author Minh Nguyen
 * @author Purodha
 */
$messages['qqq'] = array(
	'lqt-desc' => 'Short description of this extension, shown on [[Special:Version]]. Do not translate or change links.',
	'lqt_newmessages' => '{{Identical|New messages}}',
	'lqt_browse_archive_with_recent' => '{{Identical|Older}}',
	'lqt_contents_title' => '{{Identical|Contents}}',
	'lqt_new_thread' => '{{Identical|Start a new discussion}}',
	'lqt_reply' => 'is this noun or verb?

:Probably a verb; see also [[MediaWiki:Lqt delete]] and [[MediaWiki:Lqt undelete]].',
	'lqt_delete' => '{{Identical|Delete}}',
	'lqt_permalink' => 'Display name for a permanent link to the current revision of a page. When the page is edited, permalink will still link to this revision.',
	'lqt_discussion_link' => '{{Identical|Discussion}}',
	'lqt_older' => '{{Identical|Older}}',
	'lqt_hist_deleted' => '{{Identical|Deleted}}',
	'lqt_protectedfromreply_link' => '{{Identical|Protected}}',
	'lqt_subject' => '{{Identical|Subject}}',
	'lqt_noreason' => '{{Identical|No reason given}}',
	'lqt_thread_deleted_for_sysops_deleted' => '{{Identical|Deleted}}',
	'lqt_move_move' => '{{Identical|Move}}',
	'lqt_move_noreason' => '{{Identical|No reason given}}',
	'lqt_delete_return' => '{{Identical|Return to $1}}',
	'lqt_header_warning_new_discussion' => '{{Identical|Start a new discussion}}',
	'lqt-title' => '{{Identical|Title}}',
	'lqt-summary' => '{{Identical|Summary}}',
	'lqt-older' => '{{Identical|Older}}',
);

/** Faeag Rotuma (Faeag Rotuma)
 * @author Jose77
 */
$messages['rtm'] = array(
	'lqt_newmessages' => "Fäeag fo'ou",
	'lqt_discussion_link' => 'hạifäega',
);

/** Karelian (Karjala)
 * @author Flrn
 */
$messages['krl'] = array(
	'lqt_discussion_link' => 'keskustelu',
);

/** Eastern Mari (Олык Марий)
 * @author Сай
 */
$messages['mhr'] = array(
	'lqt_delete' => 'Шӧраш',
);

/** Niuean (ko e vagahau Niuē)
 * @author Jose77
 */
$messages['niu'] = array(
	'lqt_newmessages' => 'Tau tohi foou',
	'lqt_delete' => 'Tamate',
	'lqt_discussion_link' => 'fakatutala',
	'lqt_move_move' => 'Une',
);

/** Afrikaans (Afrikaans)
 * @author Arnobarnard
 */
$messages['af'] = array(
	'lqt_contents_title' => 'Inhoud:',
	'lqt_delete' => 'Skrap',
	'lqt_newer' => '«nuwer',
	'lqt_older' => 'ouer»',
	'lqt_changes_from' => '  vanaf',
	'lqt_changes_summary_of' => '  van',
	'lqt_move_move' => 'Skuif',
);

/** Arabic (العربية)
 * @author Meno25
 */
$messages['ar'] = array(
	'lqt-desc' => 'يضيف نقاشات في مجموعات إلى صفحات النقاش',
	'lqt-nothread' => 'لا توجد مجموعات رسائل في الأرشيف.',
	'lqt_newmessages' => 'رسائل جديدة',
	'lqt_movethread' => 'نقل الرسائل إلى صفحة أخرى',
	'lqt_deletethread' => 'حذف أو استرجاع الرسائل',
	'lqt_browse_archive_without_recent' => 'عرض مجموعة الرسائل المؤرشفة',
	'lqt_browse_archive_with_recent' => 'أقدم',
	'lqt_recently_archived' => 'مؤرشف حديثا:',
	'lqt_contents_title' => 'المحتويات:',
	'lqt_add_header' => 'أضف عنوانا',
	'lqt_new_thread' => 'ابدأ نقاشا جديدا',
	'lqt_in_response_to' => 'كرد على $1 بواسطة $2، بالأعلى:',
	'lqt_edited_notice' => 'معدلة',
	'lqt_move_placeholder' => 'مجموعة الرسائل هذه هي لحجز المكان وتعني أن مجموعة رسائل، $1، أزيلت من هذه الصفحة إلى صفحة نقاش أخرى.
هذا النقل تم بواسطة $2 في $3.',
	'lqt_reply' => 'رد',
	'lqt_delete' => 'احذف',
	'lqt_undelete' => 'استرجع',
	'lqt_permalink' => 'وصلة دائمة',
	'lqt_fragment' => 'جزء من $1 من $2',
	'lqt_discussion_link' => 'نقاش',
	'lqt_from_talk' => 'من $1',
	'lqt_newer' => '«أحدث',
	'lqt_older' => 'أقدم»',
	'lqt_hist_comment_edited' => 'تم تعديل نص التعليق',
	'lqt_hist_summary_changed' => 'تم تغيير الملخص',
	'lqt_hist_reply_created' => 'تم إنشاء رد جديد',
	'lqt_hist_thread_created' => 'تم إنشاء مجموعة رسائل جديدة',
	'lqt_hist_deleted' => 'محذوف',
	'lqt_hist_undeleted' => 'مسترجع',
	'lqt_hist_moved_talkpage' => 'منقول',
	'lqt_hist_listing_subtitle' => 'عرض قائمة تاريخ',
	'lqt_hist_view_whole_thread' => 'عرض تاريخ مجموعة الرسائل بأكملها',
	'lqt_hist_no_revisions_error' => 'مجموعة الرسائل هذه لا تمتلك أي نسخ في التاريخ. هذا غريب جدا.',
	'lqt_hist_past_last_page_error' => 'أنت تجاوزت عدد صفحات التاريخ الموجودة.',
	'lqt_hist_tooltip_newer_disabled' => 'هذه الوصلة معطلة لأنك على الصفحة الأولى.',
	'lqt_hist_tooltip_older_disabled' => 'هذه الوصلة معطلة لأنك على الصفحة الأخيرة.',
	'lqt_revision_as_of' => 'المراجعة بتاريخ $1.',
	'lqt_change_new_thread' => 'هذه هي المراجعة الابتدائية لمجموعة الرسائل.',
	'lqt_change_reply_created' => 'التعليق المظلل تم إنشاؤه في هذه المراجعة.',
	'lqt_change_edited_root' => 'التعليق المظلل تم تعديله في هذه المراجعة.',
	'lqt_youhavenewmessages' => 'لديك [$1 رسالة جديدة].',
	'lqt_changes_from' => ' من',
	'lqt_changes_summary_of' => ' ل',
	'lqt_protectedfromreply' => 'مجموعة الرسائل هذه تم $1 من الرد عليها.',
	'lqt_protectedfromreply_link' => 'حمايتها',
	'lqt_subject' => 'موضوع:',
	'lqt_nosubject' => '«لا موضوع»',
	'lqt_noreason' => 'لا سبب معطى.',
	'lqt_thread_deleted_for_sysops' => 'مجموعة الرسائل هذه تم $1 وهي مرئية فقط لمديري النظام.',
	'lqt_thread_deleted_for_sysops_deleted' => 'حذفها',
	'lqt_thread_deleted' => 'مجموعة الرسائل هذه تم حذفها.',
	'lqt_summary_notice' => 'لا توجد تغييرات لهذا النقاش لفترة $2 يوم على الأقل.
لو كان النقاش قد انتهى، فربما ترغب في $1.',
	'lqt_summary_notice_link' => 'اكتب ملخصا',
	'lqt_summary_label' => 'مجموعة الرسائل هذه تم تلخيصها كالتالي:',
	'lqt_summary_subtitle' => 'ملخص $1.',
	'lqt_nosuchrevision' => 'لا توجد نسخة كهذه من مجموعة الرسائل هذه.',
	'lqt_nosuchthread' => 'لا توجد مجموعة رسائل كهذه.',
	'lqt_threadrequired' => 'يجب عليك تحديد مجموعة رسائل في المسار.',
	'lqt_move_movingthread' => 'نقل $1. مجموعة الرسائل هذه هي جزء من $2.',
	'lqt_move_torename' => "لإعادة تسمية مجموعة الرسائل هذه، $1 وغير حقل 'الموضوع'.",
	'lqt_move_torename_edit' => 'عدله',
	'lqt_move_destinationtitle' => 'عنوان صفحة النقاش الهدف:',
	'lqt_move_move' => 'نقل',
	'lqt_move_nodestination' => 'يجب عليك تحديد وجهة.',
	'lqt_move_noreason' => 'لا سبب معطى.',
	'lqt_move_success' => 'مجموعة الرسائل تم نقلها إلى $1.',
	'lqt_delete_undeleting' => 'استرجاع $1.',
	'lqt_delete_undeletethread' => 'استرجاع مجموعة الرسائل',
	'lqt_delete_partof' => 'مجموعة الرسائل هذه هي جزء من $1.',
	'lqt_delete_deleting' => 'حذف $1 و $2 إليه.',
	'lqt_delete_deleting_allreplies' => 'كل الردود',
	'lqt_delete_deletethread' => 'احذف مجموعة الرسائل والردود',
	'lqt_delete_deleted' => 'تم حذف مجموعة الرسائل.',
	'lqt_delete_undeleted' => 'تم استرجاع مجموعة الرسائل.',
	'lqt_delete_return' => 'ارجع إلى $1.',
	'lqt_delete_return_link' => 'صفحة النقاش',
	'lqt_delete_unallowed' => 'أنت غير مسموح لك بحذف مجموعات الرسائل.',
	'lqt_talkpage_autocreate_summary' => 'صفحة النقاش تم إنشاؤها تلقائيا عندما تم إرسال أول مجموعة رسائل.',
	'lqt_header_warning_big' => 'أنت تعدل $1.',
	'lqt_header_warning_after_big' => 'العناوين للإعلانات والمقدمات.
ربما ترغب كبديل في $2.',
	'lqt_header_warning_bold' => 'عنوان صفحة نقاش',
	'lqt_header_warning_new_discussion' => 'بدء نقاش جديد',
	'lqt_sorting_order' => 'طريقة الترتيب:',
	'lqt_remember_sort' => 'تذكر هذا التفضيل',
	'lqt_sort_newest_changes' => 'المعدل أخيرا أولا',
	'lqt_sort_newest_threads' => 'مجموعة الرسائل الأجدد أولا',
	'lqt_sort_oldest_threads' => 'مجموعة الرسائل الأقدم أولا',
	'lqt-any-date' => 'أي تاريخ',
	'lqt-only-date' => 'فقط هذه التواريخ',
	'lqt-date-from' => 'من',
	'lqt-date-to' => 'إلى',
	'lqt-title' => 'العنوان',
	'lqt-summary' => 'ملخص',
	'lqt-older' => 'أقدم',
	'lqt-newer' => 'أجدد',
	'lqt-searching' => 'بحث عن مجموعات الرسائل',
	'lqt-read-message' => 'قراءة',
	'lqt-read-message-tooltip' => 'أزل مجموعة الرسائل هذه من الرسائل الجديدة.',
	'lqt-marked-read' => "مجموعة الرسائل '''$1''' تم التعليم عليها كمقروءة.",
	'lqt-count-marked-read' => '$1 {{PLURAL:$1|رسالة|رسالة}} تم التعليم عليها كمقروءة.',
	'lqt-email-undo' => 'رجوع',
	'lqt-messages-sent' => 'الرسائل المرسلة إليك:',
	'lqt-other-messages' => 'الرسائل في صفحات النقاش الأخرى:',
	'lqt-new-messages' => 'توجد رسائل جديدة لك.',
	'lqt-email-info-undo' => 'إعادة مجموعة الرسائل التي صرفتها منذ قليل.',
	'lqt-date-info' => 'هذه الوصلة معطلة لأنك ترى مجموعات الرسائل من كل التواريخ.',
);

/** Southern Balochi (بلوچی مکرانی)
 * @author Mostafadaneshvar
 */
$messages['bcc'] = array(
	'lqt_youhavenewmessages' => 'شما را هست $1.',
);

/** Bulgarian (Български)
 * @author DCLXVI
 * @author Spiritia
 */
$messages['bg'] = array(
	'lqt_newmessages' => 'Нови съобщения',
	'lqt_movethread' => 'Преместване на нишка към друга страница',
	'lqt_deletethread' => 'Изтриване или възстановяване на нишка',
	'lqt_browse_archive_without_recent' => 'Преглед на архивираните нишки',
	'lqt_recently_archived' => 'Наскоро архивирани',
	'lqt_contents_title' => 'Съдържание:',
	'lqt_new_thread' => 'Започване на нова дискусия.',
	'lqt_reply' => 'Отговор',
	'lqt_delete' => 'Изтриване',
	'lqt_undelete' => 'Възстановяване',
	'lqt_permalink' => 'Постоянна препратка',
	'lqt_discussion_link' => 'обсъждане',
	'lqt_from_talk' => 'от $1',
	'lqt_newer' => '« по-нови',
	'lqt_older' => 'по-стари »',
	'lqt_hist_summary_changed' => 'Променено резюме',
	'lqt_hist_tooltip_newer_disabled' => 'Препратката е неактивна, тъй като сте на първата страница.',
	'lqt_hist_tooltip_older_disabled' => 'Препратката е неактивна, тъй като сте на последната страница.',
	'lqt_revision_as_of' => 'Версия към $1.',
	'lqt_youhavenewmessages' => 'Имате [$1 ново/нови съобщение/съобщения].',
	'lqt_changes_from' => ' от',
	'lqt_changes_summary_of' => '  от',
	'lqt_protectedfromreply' => 'Тази нишка е била $1 за следващи коментари.',
	'lqt_protectedfromreply_link' => 'защитена',
	'lqt_subject' => 'Тема:',
	'lqt_nosubject' => '«няма тема»',
	'lqt_noreason' => 'Не е указана причина.',
	'lqt_thread_deleted_for_sysops' => 'Тази нишка е била $1 и е видима само за администратори.',
	'lqt_thread_deleted_for_sysops_deleted' => 'изтрита',
	'lqt_thread_deleted' => 'Нишката е била изтрита.',
	'lqt_nosuchrevision' => 'Не съществува такава версия на тази нишка.',
	'lqt_nosuchthread' => 'Няма такава нишка.',
	'lqt_threadrequired' => 'Трябва да се укаже нишка в URL.',
	'lqt_move_movingthread' => 'Преместване на $1. Тази нишка е част от $2.',
	'lqt_move_torename' => 'За преименуване на тази нишка е необходимо да $1 и променете полето „Тема“.',
	'lqt_move_torename_edit' => 'редактиране',
	'lqt_move_destinationtitle' => 'Заглавие на целевата дискусионна страница:',
	'lqt_move_move' => 'Преместване',
	'lqt_move_nodestination' => 'Необходимо е да се посочи цел.',
	'lqt_move_noreason' => 'Не е указана причина.',
	'lqt_move_success' => 'Нишката беше преместена като $1.',
	'lqt_delete_undeleting' => 'Възстановяване на $1.',
	'lqt_delete_undeletethread' => 'Възстановяване на нишка',
	'lqt_delete_partof' => 'Тази нишка е част от $1.',
	'lqt_delete_deleting' => 'Изтриване на $1 и прилежащата $2.',
	'lqt_delete_deleting_allreplies' => 'всички отговори',
	'lqt_delete_deletethread' => 'Изтриване на нишката и отговорите',
	'lqt_delete_deleted' => 'Нишката беше изтрита.',
	'lqt_delete_undeleted' => 'Нишката беше възстановена.',
	'lqt_delete_return' => 'Връщане към $1.',
	'lqt_delete_return_link' => 'беседата',
	'lqt_delete_unallowed' => 'Нямате права да изтривате нишки.',
	'lqt_header_warning_big' => 'Редактирате $1.',
	'lqt_header_warning_new_discussion' => 'започване на ново обсъждане',
	'lqt_sorting_order' => 'Ред за сортиране:',
	'lqt_remember_sort' => 'Запомняне на това предпочитание',
	'lqt_sort_newest_changes' => 'първо последно променените',
	'lqt_sort_newest_threads' => 'първо най-новите нишки',
	'lqt_sort_oldest_threads' => 'първо най-старите нишки',
	'lqt-date-from' => 'От',
	'lqt-date-to' => 'До',
	'lqt-title' => 'Заглавие',
	'lqt-summary' => 'Резюме',
	'lqt-older' => 'по-стари',
	'lqt-newer' => 'по-нови',
	'lqt-searching' => 'Търсене за нишки',
	'lqt-read-message' => 'Прочитане',
);

/** Bengali (বাংলা)
 * @author Zaheen
 */
$messages['bn'] = array(
	'lqt_delete' => 'মুছে ফেলা হোক',
);

/** Breton (Brezhoneg)
 * @author Fulup
 */
$messages['br'] = array(
	'lqt_delete' => 'Diverkañ',
);

/** Catalan (Català)
 * @author Jordi Roqué
 * @author SMP
 */
$messages['ca'] = array(
	'lqt_contents_title' => 'Contingut:',
	'lqt_discussion_link' => 'discussió',
);

/** Chamorro (Chamoru)
 * @author Gadao01
 * @author Jatrobat
 */
$messages['ch'] = array(
	'lqt_discussion_link' => 'kombetsasion',
);

/** Church Slavic (Словѣ́ньскъ / ⰔⰎⰑⰂⰡⰐⰠⰔⰍⰟ)
 * @author ОйЛ
 */
$messages['cu'] = array(
	'lqt_delete' => 'поничьжє́ниѥ',
	'lqt_protectedfromreply_link' => 'ꙁабранєно ѥ́стъ',
);

/** Welsh (Cymraeg)
 * @author Lloffiwr
 */
$messages['cy'] = array(
	'lqt_noreason' => 'Dim rheswm wedi ei roi',
	'lqt_move_noreason' => 'Dim rheswm wedi ei roi',
);

/** Danish (Dansk)
 * @author Jon Harald Søby
 */
$messages['da'] = array(
	'lqt_delete' => 'Slet',
	'lqt_discussion_link' => 'diskussion',
	'lqt_hist_deleted' => 'Slettet',
	'lqt_subject' => 'Emne:',
	'lqt_thread_deleted_for_sysops_deleted' => 'slettet',
	'lqt_delete_return' => 'Tilbage til $1.',
);

/** German (Deutsch)
 * @author DaSch
 * @author Raimond Spekking
 */
$messages['de'] = array(
	'lqt-desc' => 'Benutzung von Threads für Diskussionsseiten hinzufügen',
	'lqt-nothread' => 'Es sind keine archivierten Threads vorhanden.',
	'lqt_newmessages' => 'Neue Nachrichten',
	'lqt_movethread' => 'Verschiebe Diskussionsstrang auf eine andere Seite',
	'lqt_deletethread' => 'Lösche oder stelle Diskussionsstrang wieder her',
	'lqt_browse_archive_without_recent' => 'Archivierte Diskussionsstränge ansehen',
	'lqt_browse_archive_with_recent' => 'ältere',
	'lqt_recently_archived' => 'Kürzlich archiviert:',
	'lqt_contents_title' => 'Inhalt:',
	'lqt_add_header' => 'Ergänze Überschrift',
	'lqt_new_thread' => 'Beginne eine neue Diskussion',
	'lqt_in_response_to' => 'In Antwort auf $1 von $2, siehe:',
	'lqt_edited_notice' => 'Bearbeitet',
	'lqt_move_placeholder' => 'Dieser Thread ist ein Platzhalter um anzuzeigen, dass ein Thread, $1, von dieser Seite auf eine andere Diskussionsseite verschoben wurde. Die Verschiebung erfolgte durch $2 am $3.',
	'lqt_reply' => 'Antworten',
	'lqt_delete' => 'Löschen',
	'lqt_undelete' => 'wiederherstellen',
	'lqt_permalink' => 'Permanentlink',
	'lqt_fragment' => 'ein Fragment einer $1 von $2',
	'lqt_discussion_link' => 'Diskussion',
	'lqt_from_talk' => 'von $1',
	'lqt_newer' => '← jüngere',
	'lqt_older' => 'ältere →',
	'lqt_hist_comment_edited' => 'Kommentartext editiert',
	'lqt_hist_summary_changed' => 'Zusammenfassung geändert',
	'lqt_hist_reply_created' => 'Neue Antwort erstellt',
	'lqt_hist_thread_created' => 'Neuer Diskussionsstrang erstellt',
	'lqt_hist_deleted' => 'gelöscht',
	'lqt_hist_undeleted' => 'wiederhergestellt',
	'lqt_hist_moved_talkpage' => 'verschoben',
	'lqt_hist_listing_subtitle' => 'Ansehen einer Versionsgeschichte',
	'lqt_hist_view_whole_thread' => 'Versionsgeschichte eines ganzen Diskussionsstrangs ansehen',
	'lqt_hist_no_revisions_error' => 'Dieser Diskussionsstrang hat keine Versionsgeschichte. Das ist sehr merkwürdig',
	'lqt_hist_past_last_page_error' => 'Du bist ausserhalb des Seitenbereichs der Versionsgeschichte.',
	'lqt_hist_tooltip_newer_disabled' => 'Der Link ist deaktiviert, da du dich auf der ersten Seite befindest.',
	'lqt_hist_tooltip_older_disabled' => 'Der Link ist deaktiviert, da du dich auf der letzten Seite befindest.',
	'lqt_revision_as_of' => 'Versionsgeschichte von $1.',
	'lqt_change_new_thread' => 'Dies ist die erste Version des Diskussionsstrangs.',
	'lqt_change_reply_created' => 'Der hervorgehobene Kommentar wurde in dieser Version erstellt.',
	'lqt_change_edited_root' => 'Der hervorgehobene Kommentar wurde in dieser Version bearbeitet.',
	'lqt_youhavenewmessages' => 'Du hast [$1 neue Nachrichten].',
	'lqt_changes_from' => ' von',
	'lqt_changes_summary_of' => ' von',
	'lqt_protectedfromreply' => 'Dieser Diskussionsstrang wurde $1. Eine Antwort ist nicht möglich.',
	'lqt_protectedfromreply_link' => 'geschützt',
	'lqt_subject' => 'Thema:',
	'lqt_nosubject' => '«kein Thema»',
	'lqt_noreason' => 'kein Grund angegeben.',
	'lqt_thread_deleted_for_sysops' => 'Dieser Thread wurde $1 und ist nur für Administratoren sichtbar.',
	'lqt_thread_deleted_for_sysops_deleted' => 'gelöscht',
	'lqt_thread_deleted' => 'Thread wurde gelöscht.',
	'lqt_summary_notice' => 'Zu dieser Diskussion gibt es seit $2 Tagen keine neuen Beiträge.
Wenn die Diskussion beendet ist, möchtest du sie vielleicht $1.',
	'lqt_summary_notice_link' => 'Zusammenfassung schreiben',
	'lqt_summary_label' => 'Zusammenfassung:',
	'lqt_summary_subtitle' => 'Zusammenfassung von $1.',
	'lqt_nosuchrevision' => 'Version des Threads wurde nicht gefunden.',
	'lqt_nosuchthread' => 'Thread wurde nicht gefunden.',
	'lqt_threadrequired' => 'In der URL muss ein Thread angegeben werden.',
	'lqt_move_movingthread' => 'Verschiebe $1. Dieser Thread ist Teil von $2.',
	'lqt_move_torename' => "Um den Thread umzubennenen, $1 und ändere das 'Thema'.",
	'lqt_move_torename_edit' => 'bearbeite ihn',
	'lqt_move_destinationtitle' => 'Name der Diskussionsseite:',
	'lqt_move_move' => 'Verschieben',
	'lqt_move_nodestination' => 'Es muss eine Zielseite angegeben werden.',
	'lqt_move_noreason' => 'kein Grund angegeben.',
	'lqt_move_success' => 'Thread verschoben nach $1.',
	'lqt_delete_undeleting' => 'Wiederherstellen $1.',
	'lqt_delete_undeletethread' => 'Wiederhergestellter Thread',
	'lqt_delete_partof' => 'Dieser Thread ist Teil von $1.',
	'lqt_delete_deleting' => 'Löschung von $1 und $2.',
	'lqt_delete_deleting_allreplies' => 'alle Antworten',
	'lqt_delete_deletethread' => 'Thread und Antworten löschen',
	'lqt_delete_deleted' => 'Der Thread wurde gelöscht.',
	'lqt_delete_undeleted' => 'Der Thread wurde wiederhergestellt',
	'lqt_delete_return' => 'Zurück zu $1.',
	'lqt_delete_return_link' => 'die Diskussionsseite',
	'lqt_delete_unallowed' => 'Du hast nicht die Berechtigungen Threads zu löschen.',
	'lqt_talkpage_autocreate_summary' => 'Diskussionsseite automatisch mit dem ersten Thread erstellt.',
	'lqt_header_warning_big' => 'Du bearbeitest eine $1.',
	'lqt_header_warning_after_big' => 'Kopfzeilen sind für Ankündigungen und Einleitungen.
Möglicherweise willst du statt dessen eine $2.',
	'lqt_header_warning_bold' => 'Diskussionsseiten Kopfzeile',
	'lqt_header_warning_new_discussion' => 'neue Diskussion beginnen',
	'lqt_sorting_order' => 'Sortierung:',
	'lqt_remember_sort' => 'Einstellungen merken',
	'lqt_sort_newest_changes' => 'zuletzt geänderten Thread zuerst',
	'lqt_sort_newest_threads' => 'neuesten Thread zuerst',
	'lqt_sort_oldest_threads' => 'ältesten Thread zuerst',
	'lqt-any-date' => 'Jedes Datum',
	'lqt-only-date' => 'Nur diese Daten:',
	'lqt-date-from' => 'Von',
	'lqt-date-to' => 'Bis',
	'lqt-title' => 'Titel',
	'lqt-summary' => 'Zusammenfassung',
	'lqt-older' => 'älter',
	'lqt-newer' => 'neuer',
	'lqt-searching' => 'Suche nach Threads',
	'lqt-read-message' => 'Gelesen',
	'lqt-read-message-tooltip' => 'Entferne diesen Thread aus den neuen Nachrichten.',
	'lqt-marked-read' => "Thread '''$1''' wurde als gelesen markiert.",
	'lqt-count-marked-read' => '$1 {{PLURAL:$1|Nachricht|Nachrichten}} als gelesen markiert.',
	'lqt-email-undo' => 'Rückgängig',
	'lqt-messages-sent' => 'An dich gesendete Nachrichten:',
	'lqt-other-messages' => 'Nachrichten auf anderen Diskussionsseiten:',
	'lqt-new-messages' => 'Du hast neue Nachrichten.',
);

/** Ewe (Eʋegbe)
 * @author Natsubee
 */
$messages['ee'] = array(
	'lqt_newmessages' => 'Du yeyewo',
	'lqt_delete' => 'Tutui',
	'lqt_move_move' => 'Ɖɔli eƒe nɔƒe',
	'lqt_delete_return' => 'Gbugbɔ yi $1.',
);

/** Greek (Ελληνικά)
 * @author Consta
 */
$messages['el'] = array(
	'lqt_newmessages' => 'Νέα Μηνύματα',
	'lqt_contents_title' => 'Περιεχόμενα:',
	'lqt_new_thread' => 'Ξεκινήστε μια νέα συζήτηση',
	'lqt_reply' => 'Απάντηση',
	'lqt_delete' => 'Διαγράψτε',
	'lqt_discussion_link' => 'συζήτηση',
	'lqt_from_talk' => 'από $1',
	'lqt_youhavenewmessages' => 'Έχετε $1.',
	'lqt_changes_from' => 'από',
	'lqt_changes_summary_of' => 'από',
	'lqt_protectedfromreply_link' => 'προστατευμένο',
	'lqt_subject' => 'Θέμα:',
	'lqt_nosubject' => '«Δεν υπάρχει θέμα»',
	'lqt_noreason' => 'Δεν δίνετε λόγος.',
	'lqt_summary_notice_link' => 'γράψτε μια περίληψη',
	'lqt_move_torename_edit' => 'επεξεργαστείτε το',
	'lqt_move_noreason' => 'Δεν δίνεται λόγος.',
	'lqt_delete_return' => 'Επιστρέψτε στο $1',
	'lqt_delete_return_link' => 'Η σελίδα συζήτησης',
	'lqt_header_warning_big' => 'Επεξεργάζεστε το $1.',
	'lqt_header_warning_new_discussion' => 'ξεκινήστε μια νέα συζήτηση',
);

/** Esperanto (Esperanto)
 * @author Yekrats
 */
$messages['eo'] = array(
	'lqt-desc' => 'Aldonas fadenajn diskutojn al diskuto-paĝoj',
	'lqt-nothread' => 'Estas neniuj fadenoj en la arkivo.',
	'lqt_newmessages' => 'Novaj Mesaĝoj',
	'lqt_movethread' => 'Movu fadenon al alia paĝo',
	'lqt_deletethread' => 'Forigi aŭ restarigi fadenon',
	'lqt_browse_archive_without_recent' => 'Rigardu arkivajn fadenojn',
	'lqt_browse_archive_with_recent' => 'pli malnova',
	'lqt_recently_archived' => 'Laste arkivitaj:',
	'lqt_contents_title' => 'Enhavo:',
	'lqt_add_header' => 'Aldonu titolon',
	'lqt_new_thread' => 'Kreu novan diskuton',
	'lqt_in_response_to' => 'Respondante al $1 de $2, supren:',
	'lqt_edited_notice' => 'Redaktita',
	'lqt_reply' => 'Respondu',
	'lqt_delete' => 'Forigi',
	'lqt_undelete' => 'Restarigu',
	'lqt_permalink' => 'Daŭra ligilo',
	'lqt_fragment' => 'ero de $1 de $2',
	'lqt_discussion_link' => 'diskuto',
	'lqt_from_talk' => 'de $1',
	'lqt_newer' => '«pli nova',
	'lqt_older' => 'pli malnova»',
	'lqt_hist_comment_edited' => 'Teksto de komento estis redaktita',
	'lqt_hist_summary_changed' => 'Resumo ŝanĝita',
	'lqt_hist_reply_created' => 'Nova respondo kreita',
	'lqt_hist_thread_created' => 'Nova fadeno kreita',
	'lqt_hist_deleted' => 'Forigita',
	'lqt_hist_undeleted' => 'Restarigita',
	'lqt_hist_moved_talkpage' => 'Movita',
	'lqt_hist_listing_subtitle' => 'Rigardante historian liston.',
	'lqt_hist_view_whole_thread' => 'Rigardu historion por la tuta fadeno',
	'lqt_hist_no_revisions_error' => 'Ĉi tiu fadeno ne havas ĉiujn historiajn reviziojn. Kiel stranga!',
	'lqt_hist_past_last_page_error' => 'Vi estas preter la nombro de paĝoj da historio kiu povas ekzisti.',
	'lqt_revision_as_of' => 'Revizio ekde $1.',
	'lqt_change_new_thread' => 'Jen la unua revizio de la fadeno.',
	'lqt_change_reply_created' => 'La kolorigita komento estis kreita en ĉi tiu revizio.',
	'lqt_change_edited_root' => 'La kolorigita komento estis redaktita en ĉi tiu revizio.',
	'lqt_youhavenewmessages' => 'Vi havas [$1 novajn mesaĝojn].',
	'lqt_changes_from' => 'de',
	'lqt_changes_summary_of' => 'de',
	'lqt_protectedfromreply_link' => 'protektita',
	'lqt_subject' => 'Subjekto:',
	'lqt_nosubject' => '«neniu subjekto»',
	'lqt_noreason' => 'Nenia kialo donata',
	'lqt_thread_deleted_for_sysops_deleted' => 'forigita',
	'lqt_thread_deleted' => 'Ĉi tiu fadeno estis forigita.',
	'lqt_summary_notice_link' => 'verki resumon',
	'lqt_summary_label' => 'Ĉi tiu fadeno estis resumita jene:',
	'lqt_summary_subtitle' => 'resumo de $1.',
	'lqt_nosuchrevision' => 'Ne estas tia revizio de ĉi tiu fadeno.',
	'lqt_nosuchthread' => 'Estas neniel fadeno.',
	'lqt_threadrequired' => 'Vi devas enigi fadenon en la URL-o.',
	'lqt_move_movingthread' => 'Movante $1. Ĉi tiu fadeno estas parto de $2.',
	'lqt_move_torename_edit' => 'redaktu ĝin',
	'lqt_move_destinationtitle' => 'Titolo de destina diskuto-paĝo:',
	'lqt_move_move' => 'Alinomigi',
	'lqt_move_nodestination' => 'Vi nepre specifigu destinon.',
	'lqt_move_noreason' => 'Nenia kialo donata',
	'lqt_move_success' => 'Ĉi tiu fadeno estis movita al $1.',
	'lqt_delete_undeleting' => 'Restarigante $1.',
	'lqt_delete_undeletethread' => 'Restarigu fadenon',
	'lqt_delete_partof' => 'Ĉi tiu fadeno estas parto de $1.',
	'lqt_delete_deleting' => 'Forigante $1 kaj $2 al ĝi.',
	'lqt_delete_deleting_allreplies' => 'ĉiuj respondoj',
	'lqt_delete_deletethread' => 'Forigu fadenon kaj respondojn',
	'lqt_delete_deleted' => 'La fadeno estis forigita.',
	'lqt_delete_undeleted' => 'La fadeno estis restarigita.',
	'lqt_delete_return' => 'Reiri al $1.',
	'lqt_delete_return_link' => 'la diskuto-paĝo',
	'lqt_delete_unallowed' => 'Vi ne estas permesita forigi fadenojn.',
	'lqt_header_warning_big' => 'Vi redaktas $1.',
	'lqt_header_warning_bold' => 'diskuto-paĝa kaptitolo',
	'lqt_header_warning_new_discussion' => 'kreu novan diskuton',
	'lqt_sorting_order' => 'Ordigo:',
	'lqt_remember_sort' => 'Memori ĉi tiun preferon',
	'lqt_sort_newest_changes' => 'laste modifitaj unue',
	'lqt_sort_newest_threads' => 'Plej novaj fadenoj unue',
	'lqt_sort_oldest_threads' => 'plej malnovaj fadenoj unue',
	'lqt-any-date' => 'Ĉiu dato',
	'lqt-date-from' => 'De',
	'lqt-date-to' => 'Al',
	'lqt-title' => 'Titolo',
	'lqt-summary' => 'Resumo',
	'lqt-older' => 'pli malnovaj',
	'lqt-newer' => 'pli novaj',
	'lqt-read-message' => 'Legi',
	'lqt-count-marked-read' => '$1 {{PLURAL:$1|mesaĝo|mesaĝoj}} estis markitaj kiel legitaj.',
	'lqt-email-undo' => 'Malfari',
	'lqt-messages-sent' => 'Mesaĝoj senditaj al vi:',
	'lqt-other-messages' => 'Mesaĝoj en aliaj diskuto-paĝoj:',
	'lqt-new-messages' => 'Jen novaj mesaĝoj por vi.',
);

/** Spanish (Español)
 * @author Piolinfax
 * @author Sanbec
 */
$messages['es'] = array(
	'lqt_delete' => 'Borrar',
	'lqt_noreason' => 'No se da ninguna razón',
	'lqt_move_noreason' => 'No se da ninguna razón',
);

/** French (Français)
 * @author Dereckson
 * @author Grondin
 * @author Sherbrooke
 * @author Urhixidur
 */
$messages['fr'] = array(
	'lqt-desc' => 'Ajoute des fils de discussion dans les pages de discussion',
	'lqt-nothread' => 'Il n’existe aucun fil de discussion dans les archives.',
	'lqt_newmessages' => 'Nouveaux messages',
	'lqt_movethread' => 'Déplacer le fil vers une autre page',
	'lqt_deletethread' => 'Effacer ou récupérer le fil',
	'lqt_browse_archive_without_recent' => 'Afficher les sujets archivés',
	'lqt_browse_archive_with_recent' => 'plus anciens',
	'lqt_recently_archived' => 'Archivé récemment',
	'lqt_contents_title' => 'Table des sujets :',
	'lqt_add_header' => 'Ajouter en-tête',
	'lqt_new_thread' => 'Lancer une nouvelle discussion',
	'lqt_in_response_to' => 'En réponse à $1 par $2, plus haut :',
	'lqt_edited_notice' => 'Modifié',
	'lqt_move_placeholder' => "Ce fil est une marque substitutive indiquant qu'un fil, $1, a été déplacé de cette page vers une autre page de discussion. Ce déplacement a été fait par $2 à $3.",
	'lqt_reply' => 'Répondre',
	'lqt_delete' => 'Effacer',
	'lqt_undelete' => 'Récupérer',
	'lqt_permalink' => 'Permalien',
	'lqt_fragment' => 'un fragment de $1 à partir de $2',
	'lqt_discussion_link' => 'discussion',
	'lqt_from_talk' => 'de $1',
	'lqt_newer' => '« plus récent',
	'lqt_older' => 'plus ancien »',
	'lqt_hist_comment_edited' => 'Commentaire modifié',
	'lqt_hist_summary_changed' => 'Sommaire modifié',
	'lqt_hist_reply_created' => 'Nouvelle réponse créée',
	'lqt_hist_thread_created' => 'Nouveau fil créé',
	'lqt_hist_deleted' => 'Effacé',
	'lqt_hist_undeleted' => 'Récupéré',
	'lqt_hist_moved_talkpage' => 'Déplacé',
	'lqt_hist_listing_subtitle' => 'Visualiser un historique',
	'lqt_hist_view_whole_thread' => "Voir l'historique de tout le fil",
	'lqt_hist_no_revisions_error' => "Ce fil n'a pas d'historique de révisions. C'est bizarre.",
	'lqt_hist_past_last_page_error' => "Vous avez dépassé le nombre de pages de l'historique.",
	'lqt_hist_tooltip_newer_disabled' => 'Ce lien est inactif car vous êtes sur la première page.',
	'lqt_hist_tooltip_older_disabled' => 'Ce lien est inactif car vous êtes sur la dernière page.',
	'lqt_revision_as_of' => 'Révision en date du $1',
	'lqt_change_new_thread' => "C'est la première révision du fil.",
	'lqt_change_reply_created' => 'Le commentaire en surbrillance a été créé dans cette révision.',
	'lqt_change_edited_root' => 'Le commentaire en surbrillance a été modifié dans cette révision.',
	'lqt_youhavenewmessages' => 'Vous avez [$1 {{PLURAL:$1|nouveau message|nouveaux messages}}].',
	'lqt_changes_from' => ' de',
	'lqt_changes_summary_of' => ' de',
	'lqt_protectedfromreply' => 'Ce fil a été $1. Vous ne pouvez y répondre.',
	'lqt_protectedfromreply_link' => 'protégé',
	'lqt_subject' => 'Sujet :',
	'lqt_nosubject' => '« Aucun sujet »',
	'lqt_noreason' => 'Aucun raison donnée',
	'lqt_thread_deleted_for_sysops' => 'Ce fil a été $1. Seuls les administrateurs peuvent le voir.',
	'lqt_thread_deleted_for_sysops_deleted' => 'effacé',
	'lqt_thread_deleted' => 'Ce fil a été effacé.',
	'lqt_summary_notice' => 'Il n’y a eu aucun changement dans cette discussion depuis au moins $2 jours. Si elle a été conclue, vous pouvez avoir besoin de $1.',
	'lqt_summary_notice_link' => 'écrire un résumé',
	'lqt_summary_label' => 'Ce fil a été résumé par :',
	'lqt_summary_subtitle' => 'le résumé de $1.',
	'lqt_nosuchrevision' => 'Aucune révision pour ce fil ne correspond.',
	'lqt_nosuchthread' => 'Aucun fil ne correspond.',
	'lqt_threadrequired' => "Vous devez indiquer un fil dans l'URL.",
	'lqt_move_movingthread' => '$1 en déplacement. Ce fil fait partie de $2.',
	'lqt_move_torename' => "Pour renommer ce fil, $1 et modifier le champ ''Sujet''.",
	'lqt_move_torename_edit' => 'le modifier',
	'lqt_move_destinationtitle' => 'Titre de la page de discussion finale :',
	'lqt_move_move' => 'Déplacer',
	'lqt_move_nodestination' => 'Vous devez indiquer une destination.',
	'lqt_move_noreason' => 'Aucune raison donnée',
	'lqt_move_success' => 'Le fil a été déplacé dans $1.',
	'lqt_delete_undeleting' => 'Récupération de $1',
	'lqt_delete_undeletethread' => 'Fil récupéré',
	'lqt_delete_partof' => 'Ce fil fait partie de $1.',
	'lqt_delete_deleting' => 'Suppression du fil $1 et de $2.',
	'lqt_delete_deleting_allreplies' => 'toutes les réponses',
	'lqt_delete_deletethread' => 'Effacer le fil et répondre',
	'lqt_delete_deleted' => 'Le fil a été effacé.',
	'lqt_delete_undeleted' => 'Le fil a été récupéré.',
	'lqt_delete_return' => 'Revenir à $1',
	'lqt_delete_return_link' => 'la page de discussion',
	'lqt_delete_unallowed' => "Vous n'avez pas les droits pour effacer des fils.",
	'lqt_talkpage_autocreate_summary' => 'Page de discussion créée automatiquement quand le premier fil de discussion a été envoyé.',
	'lqt_header_warning_big' => 'Vous modifiez un $1.',
	'lqt_header_warning_after_big' => 'Les en-têtes sont pour les annonces et les préfaces. Vous devriez à la place $2.',
	'lqt_header_warning_bold' => 'En-tête d’une page de discussion',
	'lqt_header_warning_new_discussion' => 'lancer un nouveau fil de discussion',
	'lqt_sorting_order' => 'Ordre de tri :',
	'lqt_remember_sort' => 'Rappeler cette préférence',
	'lqt_sort_newest_changes' => 'en commençant par les derniers modifiés',
	'lqt_sort_newest_threads' => 'en commençant par les fils de discussion les plus récents',
	'lqt_sort_oldest_threads' => 'en commençant par les fils de discussion les plus anciens',
	'lqt-any-date' => 'Toutes les dates',
	'lqt-only-date' => 'Uniquement ces dates :',
	'lqt-date-from' => 'Du',
	'lqt-date-to' => 'au',
	'lqt-title' => 'Titre',
	'lqt-summary' => 'Sommaire',
	'lqt-older' => 'plus ancien',
	'lqt-newer' => 'plus récent',
	'lqt-searching' => 'Recherche des fils de discussion',
	'lqt-read-message' => 'Lire',
	'lqt-read-message-tooltip' => 'Retirer ce fil des nouveaux messages.',
	'lqt-marked-read' => "Fil de discussion '''$1''' marqué comme lu.",
	'lqt-count-marked-read' => '$1 {{PLURAL:$1|message marqué comme lu|messages marqués comme lus}}',
	'lqt-email-undo' => 'Défaire',
	'lqt-messages-sent' => 'Messages vous étant envoyés :',
	'lqt-other-messages' => 'Messages sur d’autres pages de discussion :',
	'lqt-new-messages' => 'Vous avez de nouveaux messages.',
	'lqt-email-info-undo' => 'Rétablir le fil que vous venez juste d’enlever.',
	'lqt-date-info' => 'Ce lien est désactivé parce que vous êtes en train de voir les fils à partir de toutes les dates.',
);

/** Western Frisian (Frysk)
 * @author Snakesteuben
 */
$messages['fy'] = array(
	'lqt_delete' => 'Wiskje',
	'lqt_move_move' => 'Werneam',
);

/** Galician (Galego)
 * @author Alma
 * @author Toliño
 * @author Xosé
 */
$messages['gl'] = array(
	'lqt-desc' => 'Engadir os fíos de discusión ás páxinas de conversa',
	'lqt-nothread' => 'Non hai fíos no arquivo.',
	'lqt_newmessages' => 'Mensaxes novas',
	'lqt_movethread' => 'Mover o fío a outra páxina',
	'lqt_deletethread' => 'Eliminar ou recuperar fío',
	'lqt_browse_archive_without_recent' => 'Ver os fíos arquivados',
	'lqt_browse_archive_with_recent' => 'máis vello',
	'lqt_recently_archived' => 'Arquivado recentemente:',
	'lqt_contents_title' => 'Contidos:',
	'lqt_add_header' => 'Engadir cabeceira',
	'lqt_new_thread' => 'Comezar un novo debate',
	'lqt_in_response_to' => 'En resposta a $1 por $2, arriba:',
	'lqt_edited_notice' => 'Editado',
	'lqt_move_placeholder' => 'Este fío é un substituto que indica que se eliminou un fío, $1, desta páxina para outra páxina de conversa. Este traslado fíxoo $2 en $3.',
	'lqt_reply' => 'Resposta',
	'lqt_delete' => 'Eliminar',
	'lqt_undelete' => 'Recuperar',
	'lqt_permalink' => 'Ligazón permanente',
	'lqt_fragment' => 'un fragmento dun $1 de $2',
	'lqt_discussion_link' => 'conversa',
	'lqt_from_talk' => 'de $1',
	'lqt_newer' => '«máis recente',
	'lqt_older' => 'máis antigo»',
	'lqt_hist_comment_edited' => 'Editouse o texto do comentario',
	'lqt_hist_summary_changed' => 'Modificouse o resumo',
	'lqt_hist_reply_created' => 'Creouse unha nova resposta',
	'lqt_hist_thread_created' => 'Creouse un novo fío',
	'lqt_hist_deleted' => 'Eliminado',
	'lqt_hist_undeleted' => 'Recuperado',
	'lqt_hist_moved_talkpage' => 'Trasladado',
	'lqt_hist_listing_subtitle' => 'Ver unha listaxe de historial.',
	'lqt_hist_view_whole_thread' => 'Ver o historial do fío completo',
	'lqt_hist_no_revisions_error' => 'Este fío non conta con revisións do historial. É moi raro.',
	'lqt_hist_past_last_page_error' => 'Está alén do número de páxinas de historial existentes.',
	'lqt_hist_tooltip_newer_disabled' => 'Esta ligazón está desactivada porque vostede está na primeira páxina.',
	'lqt_hist_tooltip_older_disabled' => 'Esta ligazón está desactivada porque vostede está na derradeira páxina.',
	'lqt_revision_as_of' => 'Revisión en $1.',
	'lqt_change_new_thread' => 'Esta é a revisión inicial do fío.',
	'lqt_change_reply_created' => 'O comentario destacado foi creado nesta revisión.',
	'lqt_change_edited_root' => 'O comentario destacado foi editado nesta revisión.',
	'lqt_youhavenewmessages' => 'Ten [$1 novas mensaxes].',
	'lqt_changes_from' => ' de',
	'lqt_changes_summary_of' => 'de',
	'lqt_protectedfromreply' => 'Este fío de conversa foi de $1 desde que se respondeu a.',
	'lqt_protectedfromreply_link' => 'protexido',
	'lqt_subject' => 'Asunto:',
	'lqt_nosubject' => '«sen asunto»',
	'lqt_noreason' => 'Ningunha razón foi dada.',
	'lqt_thread_deleted_for_sysops' => 'Este fío foi $1 e só resulta visíbel para os administradores.',
	'lqt_thread_deleted_for_sysops_deleted' => 'eliminado',
	'lqt_thread_deleted' => 'Este fío foi eliminado.',
	'lqt_summary_notice' => 'Non houbo modificacións nesta conversa en, polo menos, $2 días. Se esta conversa parece ter rematado, ao mellor quere $1.',
	'lqt_summary_notice_link' => 'escribir un resumo',
	'lqt_summary_label' => 'Este fío foi resumido como segue:',
	'lqt_summary_subtitle' => 'o resumo de $1.',
	'lqt_nosuchrevision' => 'Non existe tal revisión neste fío.',
	'lqt_nosuchthread' => 'Non existe tal fío.',
	'lqt_threadrequired' => 'Ten que especificar un fío no URL.',
	'lqt_move_movingthread' => 'A mover $1. Este fío é parte de $2.',
	'lqt_move_torename' => 'Para mudarlle o nome a este fío, $1 e cambie o campo "Asunto".',
	'lqt_move_torename_edit' => 'editalo',
	'lqt_move_destinationtitle' => 'Título da páxina de conversa de destino:',
	'lqt_move_move' => 'Mover',
	'lqt_move_nodestination' => 'Ten que indicar un destino.',
	'lqt_move_noreason' => 'Non se deu ningún motivo.',
	'lqt_move_success' => 'O fío moveuse para $1.',
	'lqt_delete_undeleting' => 'A recuperar $1.',
	'lqt_delete_undeletethread' => 'Recuperar Fío',
	'lqt_delete_partof' => 'Este fío é parte de $1.',
	'lqt_delete_deleting' => 'Eliminando $1 e $2 para esto.',
	'lqt_delete_deleting_allreplies' => 'todas as respostas',
	'lqt_delete_deletethread' => 'Eliminar Fíos e Respostas',
	'lqt_delete_deleted' => 'Eliminouse o fío.',
	'lqt_delete_undeleted' => 'Recuperouse o fío.',
	'lqt_delete_return' => 'Voltar a "$1".',
	'lqt_delete_return_link' => 'á páxina de conversa',
	'lqt_delete_unallowed' => 'Non lle está permitido eliminar fíos.',
	'lqt_talkpage_autocreate_summary' => 'Páxina de conversa creada automaticametne cando se publicou o primeiro fío.',
	'lqt_header_warning_big' => 'Vostede está editando un $1.',
	'lqt_header_warning_after_big' => 'Son cabeceiras dos anuncios e prefacios. É posíbel que no seu lugar queiran $2.',
	'lqt_header_warning_bold' => 'Cabeceira da páxina de conversa',
	'lqt_header_warning_new_discussion' => 'comezar unha nova conversa',
	'lqt_sorting_order' => 'Organizar:',
	'lqt_remember_sort' => 'Lembrar esta preferencia',
	'lqt_sort_newest_changes' => 'os últimos modificados primeiro',
	'lqt_sort_newest_threads' => 'novos fíos primeiro',
	'lqt_sort_oldest_threads' => 'os fíos máis vellos primeiro',
	'lqt-any-date' => 'Calquera data',
	'lqt-only-date' => 'Só estas datas:',
	'lqt-date-from' => 'Desde',
	'lqt-date-to' => 'Ata',
	'lqt-title' => 'Título',
	'lqt-summary' => 'Resumo',
	'lqt-searching' => 'Procurando ameazas',
	'lqt-read-message' => 'Ler',
);

/** Gothic (𐌲𐌿𐍄𐌹𐍃𐌺)
 * @author Jocke Pirat
 */
$messages['got'] = array(
	'lqt_delete' => 'Taíran',
	'lqt_move_move' => 'Skiuban',
);

/** Gujarati (ગુજરાતી)
 * @author Dsvyas
 */
$messages['gu'] = array(
	'lqt_newmessages' => 'નવા સંદેશા',
	'lqt_browse_archive_with_recent' => 'જુના',
	'lqt_recently_archived' => 'તાજેતરમાં દફતર કરેલા',
	'lqt_contents_title' => 'સૂચિ',
	'lqt_add_header' => 'મથાળુ ઉમેરો',
	'lqt_new_thread' => 'નવી ચર્ચા શરૂ કરો',
	'lqt_in_response_to' => '$1નાં પ્રત્યુત્તરમાં $2 (ઉપર મુજબ) દ્વારા',
	'lqt_edited_notice' => 'ફેરફાર કરેલા',
	'lqt_reply' => 'પ્રત્યુત્તર',
	'lqt_delete' => 'રદ કરો',
	'lqt_undelete' => 'રદ કરેલું પાછુ લાવો',
	'lqt_permalink' => 'સ્થાયી કડી',
	'lqt_fragment' => '$2થી $1નો થોડો ભાગ',
	'lqt_discussion_link' => 'ચર્ચા',
	'lqt_from_talk' => '$1 થી',
	'lqt_newer' => '<<નવા',
	'lqt_older' => '<<જુના',
	'lqt_hist_comment_edited' => 'ટીપ્પણીમાં ફેરફાર કર્યો છે',
	'lqt_hist_summary_changed' => 'સારાંશ બદલ્યો છે',
	'lqt_hist_deleted' => 'રદ કરવામાં આવ્યું છે',
	'lqt_hist_undeleted' => 'રદ કરેલું પાછું વાળ્યું છે',
	'lqt_hist_moved_talkpage' => 'ખસેડવામાં આવ્યું છે',
	'lqt_hist_listing_subtitle' => 'ઐતિહાસિક સૂચીકરણ જોઇ રહ્યા છો',
	'lqt_hist_past_last_page_error' => 'આપ ઈતિહાસના પ્રવર્તમાન પાનાની સંખ્યા વટાવી ચુક્યા છો',
	'lqt_hist_tooltip_newer_disabled' => 'આ લિંક કામ નહી કરે, કેમકે આપ પ્રથમ પાના પર છો',
	'lqt_hist_tooltip_older_disabled' => 'આ લિંક કામ નહી કરે, કેમકે આપ અંતિમ પાના પર છો',
	'lqt_revision_as_of' => '$1 સુધીમાં સુધારેલ',
	'lqt_change_reply_created' => 'હાઇલાઇટ કરેલી ટિપ્પણીની રચના વર્તમાન સુધારા દરમ્યાન કરવામાં આવી છે',
	'lqt_change_edited_root' => 'હાઇલાઇટ કરેલી ટિપ્પણીમાં ફેરફાર વર્તમાન સુધારા દરમ્યાન કરવામાં આવ્યો  છે',
	'lqt_changes_from' => 'થી',
	'lqt_protectedfromreply_link' => 'સુરક્ષિત',
	'lqt_subject' => 'વિષય',
	'lqt_nosubject' => '<<વિષય વિના>>',
	'lqt_noreason' => 'કોઇ કારણ દર્શાવવામાં આવ્યું નથી',
	'lqt_thread_deleted_for_sysops_deleted' => 'રદ કરેલ છે',
	'lqt_summary_notice' => 'અહીં છેલ્લા $2 દિવસથી કોઇ ફેરફાર થયા નથી. જો આ ચર્ચા પૂરી થઇ ગયેલી લાગે તો, તમે $1 શકો છો.',
	'lqt_summary_notice_link' => 'સારાંશ લખો',
	'lqt_summary_subtitle' => '$1નો સારાંશ',
	'lqt_move_torename_edit' => 'ફેરફાર કરો',
	'lqt_move_move' => 'ખસેડો',
);

/** Manx (Gaelg)
 * @author MacTire02
 */
$messages['gv'] = array(
	'lqt_newmessages' => 'Çhaghteraghtyn noa',
	'lqt_hist_deleted' => 'Scryssit',
	'lqt_changes_from' => ' ass',
	'lqt_thread_deleted_for_sysops_deleted' => 'scryssit',
	'lqt_summary_notice_link' => 'screeu giare-choontey',
);

/** Hakka (Hak-kâ-fa)
 * @author Hakka
 */
$messages['hak'] = array(
	'lqt_delete' => 'Chhù-thet',
);

/** Hawaiian (Hawai`i)
 * @author Singularity
 */
$messages['haw'] = array(
	'lqt_contents_title' => 'Papa kuhikuhi',
	'lqt_discussion_link' => 'kūkākūkā',
);

/** Hindi (हिन्दी)
 * @author Kaustubh
 */
$messages['hi'] = array(
	'lqt-desc' => 'वार्ता पृष्ठ पर वार्ता के थ्रेड्स बढायें',
	'lqt-nothread' => 'इस आर्चिव्हमें थ्रेड्स नहीं हैं।',
	'lqt_newmessages' => 'नये संदेश',
	'lqt_movethread' => 'थ्रेड दुसरे पन्ने पर डालें',
	'lqt_deletethread' => 'थ्रेड हटायें या पुनर्स्थापित करें',
	'lqt_browse_archive_without_recent' => 'आर्चिव्ह किये हुए थ्रेड्स देखें',
	'lqt_browse_archive_with_recent' => 'पुराने',
	'lqt_recently_archived' => 'हाल में आर्चिव्ह किये:',
	'lqt_contents_title' => 'अनुक्रम:',
	'lqt_add_header' => 'हेडर लिखे',
	'lqt_new_thread' => 'नई वार्ता शुरू करें',
	'lqt_in_response_to' => '$2 के $1 को जवाब, उपरवाला:',
	'lqt_edited_notice' => 'संपादित',
	'lqt_reply' => 'जवाब',
	'lqt_delete' => 'हटायें',
	'lqt_undelete' => 'पुनर्स्थापित करें',
	'lqt_permalink' => 'स्थायी कड़ी',
	'lqt_fragment' => '$2 से $1 का एक टुकड़ा',
	'lqt_discussion_link' => 'वार्ता',
	'lqt_from_talk' => '$1 से',
	'lqt_newer' => '«नये',
	'lqt_older' => 'पुराने»',
	'lqt_hist_comment_edited' => 'टिप्पणी बदली',
	'lqt_hist_summary_changed' => 'संक्षिप्त ज़ानकारी बदली',
	'lqt_hist_reply_created' => 'नया जवाब बनाया',
	'lqt_hist_thread_created' => 'नया थ्रेड बनाया',
	'lqt_hist_deleted' => 'हटाया',
	'lqt_hist_undeleted' => 'पुनर्स्थापित किया',
	'lqt_hist_moved_talkpage' => 'स्थानांतरित',
	'lqt_hist_listing_subtitle' => 'इतिहास सूची देख रहें हैं।',
	'lqt_hist_view_whole_thread' => 'पूरे थ्रेड का इतिहास देखें',
	'lqt_hist_no_revisions_error' => 'इस थ्रेड के इतिहास अवतरण नहीं हैं। यह आश्चर्यकारक हैं।',
	'lqt_hist_past_last_page_error' => 'आप अस्तित्वमें होने वाले इतिहास पन्नोंकी संख्याको पार कर गये हैं।',
	'lqt_hist_tooltip_newer_disabled' => 'आप पहले पन्नेपर होने के कारण यह कड़ी इस्तेमाल नहीं कर सकतें हैं।',
	'lqt_hist_tooltip_older_disabled' => 'आप आखिरी पन्नेपर होने के कारण इस कड़ीका इस्तेमाल नहीं कर सकतें हैं।',
	'lqt_revision_as_of' => '$1 का अवतरण।',
	'lqt_change_new_thread' => 'यह इस थ्रेडका शुरुवातका अवतरण हैं।',
	'lqt_change_reply_created' => 'हाइलाइट की हुई टिप्पणी इस अवतरण में दी गई थी।',
	'lqt_change_edited_root' => 'हाइलाइट की हुई टिप्पणी इस अवतरण में बदली गई थी।',
	'lqt_youhavenewmessages' => 'आपके पास $1 हैं।',
	'lqt_changes_from' => ' से',
	'lqt_changes_summary_of' => ' का',
	'lqt_protectedfromreply' => 'यह थ्रेड जवाब देने से $1 हैं।',
	'lqt_protectedfromreply_link' => 'सुरक्षित',
	'lqt_subject' => 'विषय:',
	'lqt_nosubject' => '«विषय नहीं»',
	'lqt_noreason' => 'कारण दिया नहीं।',
	'lqt_thread_deleted_for_sysops' => 'यह थ्रेड $1 हैं और सिर्फ प्रबंधकोंको ही दिख सकता हैं।',
	'lqt_thread_deleted_for_sysops_deleted' => 'हटाया',
	'lqt_thread_deleted' => 'यह थ्रेड हटाया गया हैं।',
	'lqt_summary_notice_link' => 'संक्षिप्त ज़ानकारी लिखें',
	'lqt_summary_label' => 'इस थ्रेड को निम्नलिखित संक्षिप्त ज़ानकारी दी हुई हैं:',
	'lqt_summary_subtitle' => '$1 की संक्षिप्त ज़ानकारी।',
	'lqt_nosuchrevision' => 'इस थ्रेडका ऐसा अवतरण नहीं हैं।',
	'lqt_nosuchthread' => 'ऐसा थ्रेड नहीं हैं।',
	'lqt_threadrequired' => 'URL में थ्रेड देना आवश्यक हैं।',
	'lqt_move_movingthread' => '$1 का स्थानांतरण चल रहा हैं। यह $2 का एक घटक हैं।',
	'lqt_move_torename' => "इस थ्रेड का नाम बदलने के लिये, $1 और 'विषय' बदलें।",
	'lqt_move_torename_edit' => 'संपादित करें',
	'lqt_move_destinationtitle' => 'लक्ष्य वार्ता पृष्ठ का शीर्षक:',
	'lqt_move_move' => 'स्थानांतरण करें',
	'lqt_move_nodestination' => 'आपको लक्ष्य देना आवश्यक हैं।',
	'lqt_move_noreason' => 'कारण दिया नही॥',
	'lqt_move_success' => 'यह थ्रेड $1 पर स्थानांतरीत कर दिया गया हैं।',
	'lqt_delete_undeleting' => '$1 को पुनर्स्थापित कर रहें हैं।',
	'lqt_delete_undeletethread' => 'थ्रेड पुनर्स्थापित करें',
	'lqt_delete_partof' => 'यह थ्रेड $1 का घटक हैं।',
	'lqt_delete_deleting' => '$1 हटा रहें हैं और उसमें $2 कर रहें हैं।',
	'lqt_delete_deleting_allreplies' => 'सभी जवाब',
	'lqt_delete_deletethread' => 'थ्रेड और जवाब हटायें',
	'lqt_delete_deleted' => 'थ्रेड को हटाया गया हैं।',
	'lqt_delete_undeleted' => 'थ्रेड पुनर्स्थापित कर दिया गया हैं।',
	'lqt_delete_return' => '$1 की ओर वापस जायें।',
	'lqt_delete_return_link' => 'वार्ता पॄष्ठ',
	'lqt_delete_unallowed' => 'आपको थ्रेड हटाने की अनुमति नहीं हैं।',
	'lqt_talkpage_autocreate_summary' => 'पहला थ्रेड लिखने के बाद वार्ता पृष्ठ अपने आप बन गया था।',
	'lqt_header_warning_big' => 'आप एक $1 संपादित कर रहें हैं।',
	'lqt_header_warning_after_big' => 'हेडर सिर्फ घोषणा या प्रिफेस के लिये इस्तेमाल किया जाता हैं।
आप उसके बदलेमें $2 का इस्तेमाल कर सकतें हैं।',
	'lqt_header_warning_bold' => 'वार्ता पृष्ठ का हेडर',
	'lqt_header_warning_new_discussion' => 'नई वार्ता शुरू करें',
	'lqt_sorting_order' => 'अनुक्रम दें:',
	'lqt_remember_sort' => 'यह पसंद याद रखें',
	'lqt_sort_newest_changes' => 'आखिर में बदले गये पहले',
	'lqt_sort_newest_threads' => 'नये थ्रेड पहले',
	'lqt_sort_oldest_threads' => 'पुराने थ्रेड पहले',
);

/** Hiligaynon (Ilonggo)
 * @author Jose77
 */
$messages['hil'] = array(
	'lqt_delete' => 'Panason',
	'lqt_discussion_link' => 'Paghisayranay',
	'lqt_move_move' => 'Saylohon',
);

/** Croatian (Hrvatski)
 * @author Dalibor Bosits
 */
$messages['hr'] = array(
	'lqt_delete' => 'Izbriši',
);

/** Upper Sorbian (Hornjoserbsce)
 * @author Michawiki
 */
$messages['hsb'] = array(
	'lqt-desc' => 'Nitkowe diskusije k diskusijnym stronam přidać',
	'lqt-nothread' => 'W archiwje žane nitki njejsu.',
	'lqt_newmessages' => 'Nowe powěsće',
	'lqt_movethread' => 'Nitku na druhu stronu přesunyć',
	'lqt_deletethread' => 'Nitku wušmórnyć abo wobnowić',
	'lqt_browse_archive_without_recent' => 'Archiwowane nitki wobhladać',
	'lqt_browse_archive_with_recent' => 'starši',
	'lqt_recently_archived' => 'Runje archiwowany',
	'lqt_contents_title' => 'Wobsah:',
	'lqt_add_header' => 'Hłowu přidać',
	'lqt_new_thread' => 'Nowu diskusiju započeć',
	'lqt_in_response_to' => 'Wotmołwjejo na $1 wot $2, horjeka:',
	'lqt_edited_notice' => 'Wobdźěłany',
	'lqt_move_placeholder' => 'Tuta nitka je naměstnik, kotryž podawa, zo nitka, $1, je so z tuteje strony na druhu diskusijnu stron přesunyła. Tute přesunjenje je $2 pola $3 činił.',
	'lqt_reply' => 'Wotmołwić',
	'lqt_delete' => 'Wušmórnyć',
	'lqt_undelete' => 'Wobnowić',
	'lqt_permalink' => 'Trajny wotkaz',
	'lqt_fragment' => 'Fragment wot $1 z $2',
	'lqt_discussion_link' => 'diskusija',
	'lqt_from_talk' => 'wot $1',
	'lqt_newer' => '«nowši',
	'lqt_older' => 'starši»',
	'lqt_hist_comment_edited' => 'Změnjeny tekst komentować',
	'lqt_hist_summary_changed' => 'Zjeće změnjene',
	'lqt_hist_reply_created' => 'Nowu wotmołwu wutworjeny',
	'lqt_hist_thread_created' => 'Nowa nitka wutworjena',
	'lqt_hist_deleted' => 'Wušmórnjeny',
	'lqt_hist_undeleted' => 'Wobnowjeny',
	'lqt_hist_moved_talkpage' => 'Přesunjeny',
	'lqt_hist_listing_subtitle' => 'Lisćinu stawiznow wobhladać',
	'lqt_hist_view_whole_thread' => 'Stawizny za cyłu nitku wobhladać',
	'lqt_hist_no_revisions_error' => 'Tuta nitka nima stawizniske wersije, To je zboha dźiwne.',
	'lqt_hist_past_last_page_error' => 'Sy wyše ličby stronow stawiznow.',
	'lqt_hist_tooltip_newer_disabled' => 'Tutón wotkaz je znjemóžnjeny, dokelž sy na prěnjej stronje.',
	'lqt_hist_tooltip_older_disabled' => 'Tutón wotkaz je znjemóžnjeny, dokelž sy na poslednjej stronje.',
	'lqt_revision_as_of' => 'Wersija wot $1.',
	'lqt_change_new_thread' => 'To je spočatna wersija nitki.',
	'lqt_change_reply_created' => 'Wuzběhnjeny komentar bu w tutej wersiji wutworjeny.',
	'lqt_change_edited_root' => 'Wuzběhnjeny komentar bu w tutej wersiji změnjeny.',
	'lqt_youhavenewmessages' => 'Maš $1.',
	'lqt_changes_from' => ' wot',
	'lqt_changes_summary_of' => 'wot',
	'lqt_protectedfromreply' => 'Z tuteje nitki je $1 wotmołwił.',
	'lqt_protectedfromreply_link' => 'škitany',
	'lqt_subject' => 'Tema:',
	'lqt_nosubject' => '«žana tema»',
	'lqt_noreason' => 'Žana pričina podata.',
	'lqt_thread_deleted_for_sysops' => 'Tuta nitka je $1 była a je jenož za administratorow widźomna.',
	'lqt_thread_deleted_for_sysops_deleted' => 'wušmórnjeny',
	'lqt_thread_deleted' => 'Tuta nitka je so wušmórnyła.',
	'lqt_summary_notice' => 'Wot znajmjeńša $2 {{PLURAL:$2|dnja|dnjow|dnjow|dnjow}} na tutej diskusijnej stronje žane změny njeběchu. Jeli je zakónčena, chceš snano $1.',
	'lqt_summary_notice_link' => 'zjeće pisać',
	'lqt_summary_label' => 'Tuta nitka je so takle zjała:',
	'lqt_summary_subtitle' => 'zjeće wot $1.',
	'lqt_nosuchrevision' => 'Njeje tajka wersija tuteje nitki.',
	'lqt_nosuchthread' => 'Njeje tajka nitka.',
	'lqt_threadrequired' => 'Dyrbiš w URL nitku podać.',
	'lqt_move_movingthread' => '$1 přesunje so. Tuta nitka je dźěl wot $2.',
	'lqt_move_torename' => "Zo by tutu nitku přemjenował, $1 a změń polo 'Tema'.",
	'lqt_move_torename_edit' => 'změnić',
	'lqt_move_destinationtitle' => 'Titul ciloweje diskusijneje strony:',
	'lqt_move_move' => 'Přesunyć',
	'lqt_move_nodestination' => 'Dyrbiš cil podać.',
	'lqt_move_noreason' => 'Žana přičina podata.',
	'lqt_move_success' => 'Tuta nitka je so do $1 přesunyła.',
	'lqt_delete_undeleting' => 'Wobnowja so $1.',
	'lqt_delete_undeletethread' => 'Nitku wobnowić',
	'lqt_delete_partof' => 'Tuta nitka je dźěl wot $1.',
	'lqt_delete_deleting' => 'Wušmórnja so $1 a $2 do njeho.',
	'lqt_delete_deleting_allreplies' => 'wšě wotmołwy',
	'lqt_delete_deletethread' => 'Nitku a wotmołwy wušmórnyć.',
	'lqt_delete_deleted' => 'Nitka je so wušmórnyła.',
	'lqt_delete_undeleted' => 'Nitka je so wobnowiła.',
	'lqt_delete_return' => 'Wróć so k $1.',
	'lqt_delete_return_link' => 'diskusijna strona',
	'lqt_delete_unallowed' => 'Njesměš nitki wušmórować.',
	'lqt_talkpage_autocreate_summary' => 'Diskusijna strona je so awtomatisce wutworiła, hdyž bě so prěnja nitka wotesłała.',
	'lqt_header_warning_big' => 'Wobdźěłuješ $1.',
	'lqt_header_warning_after_big' => 'Hłowowe linki su za wozjewjenja a předsłowa. Chceš snano město toho $2.',
	'lqt_header_warning_bold' => 'Hłowowa linka diskusijneje strony',
	'lqt_header_warning_new_discussion' => 'nowu diskusiju započeć',
	'lqt_sorting_order' => 'Sortěrowanski porjad:',
	'lqt_remember_sort' => 'Tute nastajenje sej spomjatkować',
	'lqt_sort_newest_changes' => 'Poslednje změnjene jako prěnje',
	'lqt_sort_newest_threads' => 'najnowše nitki jako přenje',
	'lqt_sort_oldest_threads' => 'najstarše nitki jako prěnje',
);

/** Hungarian (Magyar)
 * @author Dani
 * @author Tgr
 */
$messages['hu'] = array(
	'lqt_newmessages' => 'Új üzenetek',
	'lqt_movethread' => 'Átmozgatás másik lapra',
	'lqt_deletethread' => 'Beszélgetés törlése vagy visszaállítása',
	'lqt_browse_archive_without_recent' => 'Archivált beszélgetések megtekintése',
	'lqt_browse_archive_with_recent' => 'korábbi',
	'lqt_recently_archived' => 'Legutóbb archivált:',
	'lqt_contents_title' => 'Tartalom',
	'lqt_add_header' => 'Fejléc hozzáadása',
	'lqt_new_thread' => 'Új beszélgetés indítása',
	'lqt_in_response_to' => 'Válasz: $1, írta $2:',
	'lqt_edited_notice' => 'szerkesztve',
	'lqt_move_placeholder' => 'Ez a beszélgetés csupán egy jelzés, hogy a $1 beszélgetést $2 áthelyezte $3-kor egy másik vitalapra.',
	'lqt_reply' => 'válasz',
	'lqt_delete' => 'törlés',
	'lqt_undelete' => 'visszaállítás',
	'lqt_permalink' => 'link erre',
	'lqt_discussion_link' => 'beszélgetés',
	'lqt_from_talk' => '$1 felhasználótól',
	'lqt_newer' => '«frissebb',
	'lqt_older' => 'korábbi»',
	'lqt_hist_comment_edited' => 'Szerkesztette a megjegyzést',
	'lqt_hist_summary_changed' => 'Megváltoztatta az összefoglalót',
	'lqt_hist_reply_created' => 'Új választ írt',
	'lqt_hist_thread_created' => 'Új beszélgetést indított',
	'lqt_hist_deleted' => 'törölve',
	'lqt_hist_undeleted' => 'visszaállítva',
	'lqt_hist_moved_talkpage' => 'áthelyezve',
	'lqt_hist_listing_subtitle' => 'Laptörténet megtekintése.',
	'lqt_hist_view_whole_thread' => 'Az egész beszélgetés laptörténetének megtekintése',
	'lqt_hist_no_revisions_error' => 'A beszélgetésnek nincs laptörténete. Ez nagyon furcsa.',
	'lqt_hist_past_last_page_error' => 'Túl vagy a laptörténet oldalainak számán.',
	'lqt_hist_tooltip_newer_disabled' => 'A link le van tiltva, mert az első lapon vagy.',
	'lqt_hist_tooltip_older_disabled' => 'A link le van tiltva, mert az utolsó lapon vagy.',
	'lqt_revision_as_of' => '$1-kori változat',
	'lqt_change_new_thread' => 'Ez a beszélgetés első változata',
	'lqt_change_reply_created' => 'A kiemelt megjegyzés ennél a változatnál készült.',
	'lqt_change_edited_root' => 'A kiemelt megjegyzés ennél a változatnál lett szerkesztve.',
	'lqt_protectedfromreply' => 'Ez a beszélgetés $1 a válaszlehetőségtől.',
	'lqt_protectedfromreply_link' => 'le van védve',
	'lqt_subject' => 'Tárgy',
	'lqt_nosubject' => '«nincs tárgy»',
	'lqt_noreason' => 'Nincs ok megadva.',
	'lqt_thread_deleted_for_sysops' => 'A beszélgetés $1 és csak adminisztrátorok számára látható.',
	'lqt_thread_deleted_for_sysops_deleted' => 'törölve lett',
	'lqt_thread_deleted' => 'A beszélgetést törölték.',
	'lqt_summary_notice' => 'Nem történt változás $2 napja. Ha a beszélgetés befejeződött, $1.',
	'lqt_summary_notice_link' => 'írhatsz összefoglalót',
	'lqt_summary_label' => 'A beszélgetést a következőképpen összegezték:',
	'lqt_summary_subtitle' => '$1 összegzését.',
	'lqt_nosuchrevision' => 'A beszélgetésnek nincs ilyen változata.',
	'lqt_nosuchthread' => 'Nincs ilyen beszélgetés.',
	'lqt_threadrequired' => 'Meg kell adnod egy beszélgetést az URL-ben',
	'lqt_move_torename' => 'A beszélgetés átnevezéséhez a $1 változtasd meg a Tárgy mezőt.',
	'lqt_move_torename_edit' => 'szerkesztéskor',
	'lqt_move_destinationtitle' => 'Cél vitalap neve',
	'lqt_move_move' => 'Áthelyezés',
	'lqt_move_nodestination' => 'Meg kell adnod a célt.',
	'lqt_move_noreason' => 'Nincs ok megadva.',
	'lqt_move_success' => 'A beszélgetés át lett helyezve a(z) $1 lapra.',
	'lqt_delete_undeleting' => '$1 visszaállítása.',
	'lqt_delete_undeletethread' => 'Beszélgetés visszaállítása',
	'lqt_delete_partof' => 'Ez a beszélgetés $1 része.',
	'lqt_delete_deleting_allreplies' => 'összes válasz',
	'lqt_delete_deletethread' => 'Beszélgetés és válaszok törlése',
	'lqt_delete_deleted' => 'A beszélgetés törölve lett.',
	'lqt_delete_undeleted' => 'A beszélgetés helyre lett állítva.',
	'lqt_delete_return' => 'Visszatérés a $1.',
	'lqt_delete_return_link' => 'vitalapra',
	'lqt_delete_unallowed' => 'Nincs jogod beszélgetéseket törölni.',
	'lqt_talkpage_autocreate_summary' => 'Vitalap automatikus elkészítése az első beszélgetés indulásakor.',
	'lqt_header_warning_big' => 'Te most egy $1 szerkesztesz.',
	'lqt_header_warning_after_big' => 'A fejlécek bejelentések és előszavak számára vannak. Nem $2 akarsz indítani?',
	'lqt_header_warning_bold' => 'vitalap-fejlécet',
	'lqt_header_warning_new_discussion' => 'új beszélgetést',
	'lqt_sorting_order' => 'Rendezés:',
	'lqt_remember_sort' => 'Beállítás megjegyzése',
	'lqt_sort_newest_changes' => 'utoljára módosított elöl',
	'lqt_sort_newest_threads' => 'legújabb beszélgetések elöl',
	'lqt_sort_oldest_threads' => 'legrégebbi beszélgetések elöl',
);

/** Interlingua (Interlingua)
 * @author McDutchie
 */
$messages['ia'] = array(
	'lqt_newmessages' => 'Nove messages',
	'lqt_delete' => 'Deler',
	'lqt_hist_deleted' => 'Delite',
	'lqt_noreason' => 'Nulle ration date.',
	'lqt_thread_deleted_for_sysops_deleted' => 'delite',
	'lqt_move_move' => 'Renominar',
	'lqt_move_noreason' => 'Nulle ration date.',
);

/** Icelandic (Íslenska)
 * @author S.Örvarr.S
 */
$messages['is'] = array(
	'lqt_noreason' => 'Engin ástæða gefin.',
	'lqt_move_noreason' => 'Engin ástæða gefin.',
);

/** Italian (Italiano)
 * @author Darth Kule
 */
$messages['it'] = array(
	'lqt_contents_title' => 'Indice:',
	'lqt_delete' => 'Cancella',
);

/** Japanese (日本語)
 * @author Aotake
 * @author JtFuruhata
 */
$messages['ja'] = array(
	'lqt-desc' => '{{int:talk}}ページをスレッド形式の議論ページにする',
	'lqt-nothread' => '保存されているスレッドはありません。',
	'lqt_newmessages' => '新しいメッセージ',
	'lqt_movethread' => 'スレッドを別のページに移動',
	'lqt_deletethread' => 'スレッドの削除と復帰',
	'lqt_browse_archive_without_recent' => '保存されているスレッドの閲覧',
	'lqt_browse_archive_with_recent' => '古いもの',
	'lqt_recently_archived' => '最近保存されたもの:',
	'lqt_contents_title' => '目次:',
	'lqt_add_header' => 'ヘッダの追加',
	'lqt_new_thread' => '新しい議論を始める',
	'lqt_in_response_to' => '$2 が発言した $1 への返答:',
	'lqt_edited_notice' => '編集済み',
	'lqt_move_placeholder' => 'このスレッドは、$1 が別ページの{{int:talk}}ページへ移動したことを示すプレースホルダです。この移動は $3 に $2 によって行われました。',
	'lqt_reply' => '返答',
	'lqt_delete' => '削除',
	'lqt_undelete' => '復帰',
	'lqt_permalink' => '固定リンク',
	'lqt_fragment' => 'これは $2 における $1 の一部です',
	'lqt_discussion_link' => '議論',
	'lqt_from_talk' => '$1 より',
	'lqt_newer' => '«新しいもの',
	'lqt_older' => '古いもの»',
	'lqt_hist_comment_edited' => 'コメントの編集',
	'lqt_hist_summary_changed' => '要約の変更',
	'lqt_hist_reply_created' => '返答の新規作成',
	'lqt_hist_thread_created' => 'スレッドの新規作成',
	'lqt_hist_deleted' => '削除',
	'lqt_hist_undeleted' => '復帰',
	'lqt_hist_moved_talkpage' => '移動',
	'lqt_hist_listing_subtitle' => '履歴リスト表示',
	'lqt_hist_view_whole_thread' => 'このスレッド全体の履歴を見る',
	'lqt_hist_no_revisions_error' => 'このスレッドには変更履歴がありません。少々奇妙なことですが。',
	'lqt_hist_past_last_page_error' => '存在する履歴ページ番号を超えた指定が行われました。',
	'lqt_hist_tooltip_newer_disabled' => '最初のページにつき、このリンクは動作しません。',
	'lqt_hist_tooltip_older_disabled' => '最後のページにつき、このリンクは動作しません。',
	'lqt_revision_as_of' => '$1の版',
	'lqt_change_new_thread' => 'このスレッドの最初の版です。',
	'lqt_change_reply_created' => 'この版で作成されたコメントがハイライト表示されています。',
	'lqt_change_edited_root' => 'この版で変更されたコメントがハイライト表示されています。',
	'lqt_youhavenewmessages' => '$1の新しいメッセージがあります。',
	'lqt_changes_from' => '  スレッド元:',
	'lqt_changes_summary_of' => '  要約先:',
	'lqt_protectedfromreply' => 'このスレッドは$1されているため返答できません。',
	'lqt_protectedfromreply_link' => '保護',
	'lqt_subject' => '表題:',
	'lqt_nosubject' => '«無題»',
	'lqt_noreason' => '理由は付記されていません。',
	'lqt_thread_deleted_for_sysops' => 'このスレッドは$1されており、{{int:group-sysop}}だけが内容を見ることができます。',
	'lqt_thread_deleted_for_sysops_deleted' => '削除',
	'lqt_thread_deleted' => 'このスレッドは削除されました。',
	'lqt_summary_notice' => 'この議論は少なくとも$2日間進展がありません。議論が終結しているなら、$1のも良いでしょう。',
	'lqt_summary_notice_link' => '要約を記述する',
	'lqt_summary_label' => 'このスレッドは以下のように要約されました:',
	'lqt_summary_subtitle' => '$1 の要約です。',
	'lqt_nosuchrevision' => '指定された版はこのスレッドに存在しません。',
	'lqt_nosuchthread' => '指定されたスレッドが存在しません。',
	'lqt_threadrequired' => 'このURLにはスレッド名を記述する必要があります。',
	'lqt_move_movingthread' => '$1 を移動します。これは $2 にあるスレッドの一部です。',
	'lqt_move_torename' => 'スレッド名を変更する場合、表題欄を$1。',
	'lqt_move_torename_edit' => '編集してください',
	'lqt_move_destinationtitle' => '移動先{{int:talk}}ページ名',
	'lqt_move_move' => '移動',
	'lqt_move_nodestination' => '移動先を指定してください。',
	'lqt_move_noreason' => '理由が付記されていません。',
	'lqt_move_success' => 'このスレッドは $1 に移動しました。',
	'lqt_delete_undeleting' => '$1 を復帰します。',
	'lqt_delete_undeletethread' => 'スレッドの復帰',
	'lqt_delete_partof' => 'これは $1 にあるスレッドの一部です。',
	'lqt_delete_deleting' => '$1 及びその$2を削除します。',
	'lqt_delete_deleting_allreplies' => '全ての返答',
	'lqt_delete_deletethread' => 'スレッドと返答の削除',
	'lqt_delete_deleted' => 'スレッドが削除されました。',
	'lqt_delete_undeleted' => 'スレッドが復帰しました。',
	'lqt_delete_return' => '$1に戻る',
	'lqt_delete_return_link' => '{{int:talk}}ページ',
	'lqt_delete_unallowed' => 'あなたはスレッドの削除処理を許可されていません。',
	'lqt_talkpage_autocreate_summary' => '最初のスレッドが始まる際、{{int:talk}}ページは自動的に作成されます。',
	'lqt_header_warning_big' => 'あなたは$1を編集しようとしています。',
	'lqt_header_warning_after_big' => '見出しは、告知や序文のためにあります。代わりに$2べきでしょう。',
	'lqt_header_warning_bold' => '議論ページの見出し',
	'lqt_header_warning_new_discussion' => '新しい議論を始める',
	'lqt_sorting_order' => '並び替え順:',
	'lqt_remember_sort' => 'この設定を記憶する',
	'lqt_sort_newest_changes' => '最終更新を上に',
	'lqt_sort_newest_threads' => '新しいスレッドを上に',
	'lqt_sort_oldest_threads' => '古いスレッドを上に',
);

/** Javanese (Basa Jawa)
 * @author Meursault2004
 */
$messages['jv'] = array(
	'lqt_newmessages' => 'Pesen anyar',
	'lqt_browse_archive_with_recent' => 'luwih lawas',
	'lqt_recently_archived' => 'Lagi waé diarsip:',
	'lqt_contents_title' => 'Isi:',
	'lqt_new_thread' => 'Miwiti dhiskusi anyar',
	'lqt_edited_notice' => 'Disunting',
	'lqt_reply' => 'Wangsulana',
	'lqt_delete' => 'Busak',
	'lqt_undelete' => 'Batalna pambusakan',
	'lqt_permalink' => 'Pranala permanèn',
	'lqt_discussion_link' => 'dhiskusi',
	'lqt_from_talk' => 'saka $1',
	'lqt_hist_comment_edited' => 'Tèks komentar disunting',
	'lqt_hist_summary_changed' => 'Ringkesané diganti',
	'lqt_hist_reply_created' => 'Nggawé wangsulan anyar',
	'lqt_hist_deleted' => 'Dibusak',
	'lqt_hist_undeleted' => 'Batal busak',
	'lqt_hist_moved_talkpage' => 'Dipindhah',
	'lqt_revision_as_of' => 'Révisi per $1.',
	'lqt_youhavenewmessages' => 'Panjenengan ana $1.',
	'lqt_changes_from' => 'saking',
	'lqt_changes_summary_of' => 'saka',
	'lqt_protectedfromreply_link' => 'direksa',
	'lqt_subject' => 'Subyèk:',
	'lqt_nosubject' => '«ora ana subyèk»',
	'lqt_noreason' => 'Ora mènèhi alesan.',
	'lqt_thread_deleted_for_sysops_deleted' => 'dibusak',
	'lqt_summary_notice_link' => 'nulis ringkesan',
	'lqt_summary_subtitle' => 'ringkesan saka $1.',
	'lqt_move_torename_edit' => 'sunting iku',
	'lqt_move_move' => 'Pindhah',
	'lqt_move_nodestination' => 'Panjenengan kudu spésifikasi tujuan.',
	'lqt_move_noreason' => 'Ora mènèhi alesan.',
	'lqt_delete_undeleting' => 'Mbatalaké pambusakan $1.',
	'lqt_delete_deleting_allreplies' => 'kabèh wangsulan',
	'lqt_delete_return' => 'Bali menyang $1.',
	'lqt_header_warning_big' => 'Panjenengan nyunting sawijining $1.',
	'lqt_header_warning_new_discussion' => 'miwiti dhiskusi anyar',
	'lqt_sorting_order' => 'Urutan sortir:',
	'lqt_remember_sort' => 'Élinga préferènsi iki',
);

/** Khmer (ភាសាខ្មែរ)
 * @author Chhorran
 * @author Lovekhmer
 * @author គីមស៊្រុន
 */
$messages['km'] = array(
	'lqt_newmessages' => 'សារថ្មីៗ',
	'lqt_browse_archive_with_recent' => 'ចាស់ជាង ៖',
	'lqt_recently_archived' => 'ទើបតែដាក់ជា បណ្ណសារ ៖',
	'lqt_contents_title' => 'មាតិកា៖',
	'lqt_add_header' => 'បន្ថែមក្បាលទំព័រ',
	'lqt_new_thread' => 'ផ្តើម មួយការពិភាក្សា ថ្មី',
	'lqt_in_response_to' => 'ឆ្លើយតប ទៅ $1 ដោយ $2, ខាងលើ ៖',
	'lqt_edited_notice' => 'បានកែប្រែ',
	'lqt_reply' => 'ឆ្លើយតប',
	'lqt_delete' => 'លុបចេញ',
	'lqt_undelete' => 'ឈប់លុបចេញ',
	'lqt_permalink' => 'តំណភ្ជាប់អចិន្ត្រៃយ៍',
	'lqt_fragment' => 'មួយបំណែក របស់ $1 ពី $2',
	'lqt_discussion_link' => 'ការពិភាក្សា',
	'lqt_from_talk' => 'ពី $1',
	'lqt_newer' => '«ថ្មីជាង',
	'lqt_older' => 'ចាស់ជាង»',
	'lqt_hist_comment_edited' => 'វិចារបានត្រូវកែប្រែ',
	'lqt_hist_summary_changed' => 'សេចក្តីសង្ខេប ត្រូវបាន ផ្លាស់ប្តូរ',
	'lqt_hist_reply_created' => 'ការឆ្លើយតបថ្មី ត្រូវបានបង្កើត',
	'lqt_hist_deleted' => 'ត្រូវបានលុបចោល',
	'lqt_hist_undeleted' => 'លែងបានត្រូវលុបចេញ',
	'lqt_hist_moved_talkpage' => 'បានប្តូរទីតាំង',
	'lqt_hist_listing_subtitle' => 'មើល បញ្ជីប្រវត្តិ ។',
	'lqt_hist_past_last_page_error' => 'អ្នកបានហួស ចំនួនទំព័រ នៃប្រវត្តិ ដែលមាន',
	'lqt_hist_tooltip_newer_disabled' => 'តំណភ្ជាប់នេះ អសកម្ម ព្រោះ អ្នកថិតលើ ទំព័រដំបូង ។',
	'lqt_hist_tooltip_older_disabled' => 'តំណភ្ជាប់នេះ អសកម្ម ព្រោះ អ្នកថិតលើ ទំព័រចុងក្រោយ ។',
	'lqt_youhavenewmessages' => 'អ្នកមាន $1​ ។',
	'lqt_changes_from' => 'ពី',
	'lqt_changes_summary_of' => 'នៃ',
	'lqt_protectedfromreply_link' => 'បានការពារ',
	'lqt_subject' => 'ប្រធានបទ ៖',
	'lqt_nosubject' => '«មិនមានប្រធានបទទេ»',
	'lqt_noreason' => 'គ្មានហេតុផល ត្រូវបានផ្តល់អោយ ។',
	'lqt_thread_deleted_for_sysops_deleted' => 'ត្រូវបានលុបចោល',
	'lqt_summary_notice' => 'គ្មានបំលាស់ប្តូរ ក្នុងការពិភាក្សានេះ តាំងពី យ៉ាងតិច $2 ថ្ងៃ ។ បើចង់ផ្លាស់ប្តូរវា, អ្នកអាចនឹង ត្រូវការ $1 ។',
	'lqt_summary_notice_link' => 'សរសេរ មួយ សេចក្តីសង្ខេប',
	'lqt_summary_subtitle' => 'សេចក្តីសង្ខេបនៃ$1 ។',
	'lqt_move_torename_edit' => 'កែប្រែវា',
	'lqt_move_destinationtitle' => 'ចំណងជើង នៃ ទំព័រពិភាក្សា​ គោលដៅ ៖',
	'lqt_move_move' => 'ប្តូរទីតាំង',
	'lqt_move_nodestination' => 'អ្នកត្រូវតែ សំដៅ មួយគោលដៅ ។',
	'lqt_move_noreason' => 'គ្មានហេតុផល ត្រូវបានផ្តល់អោយ ។',
	'lqt_delete_undeleting' => 'លែងលុបចេញ $1 ។',
	'lqt_delete_deleting_allreplies' => 'គ្រប់ ការឆ្លើយតប',
	'lqt_delete_return' => 'ត្រលប់ទៅកាន់ $1 វិញ។',
	'lqt_delete_return_link' => 'ទំព័រពិភាក្សា',
	'lqt_header_warning_big' => 'អ្នកកំពុង កែប្រែ $1 ។',
	'lqt_header_warning_bold' => 'បឋមកថានៃទំព័រពិភាក្សា',
	'lqt_header_warning_new_discussion' => 'ចាប់ផ្តើមការពិភាក្សាថ្មី',
	'lqt_sorting_order' => 'លំដាប់ រៀប ៖',
	'lqt_remember_sort' => 'ចងចាំ ចំណូលចិត្ត នេះ',
	'lqt-date-from' => 'ពី',
	'lqt-date-to' => 'ដល់',
	'lqt-title' => 'ចំនងជើង',
	'lqt-older' => 'ចាស់ជាង',
	'lqt-newer' => 'ថ្មីជាង',
	'lqt-read-message' => 'អាន',
	'lqt-messages-sent' => 'សារបានផ្ញើទៅអ្នក៖',
	'lqt-new-messages' => 'មានសារថ្មីៗសំរាប់អ្នក។',
);

/** Kinaray-a (Kinaray-a)
 * @author Jose77
 */
$messages['krj'] = array(
	'lqt_contents_title' => 'Manga Sulud:',
	'lqt_delete' => 'Para',
	'lqt_discussion_link' => 'sogdanunay',
	'lqt_delete_return' => 'Balik sa $1.',
);

/** Ripoarisch (Ripoarisch)
 * @author Purodha
 */
$messages['ksh'] = array(
	'lqt-desc' => 'Määt Jeschprääschs-Fäddeme en Klaaf-Sigge müjjelesch.',
	'lqt_delete' => 'Fottschmieße',
	'lqt_protectedfromreply_link' => 'jeschöz',
	'lqt_noreason' => 'Keine Aanlass aanjejovve.',
	'lqt_thread_deleted_for_sysops_deleted' => 'fottjeschmesse',
	'lqt_move_move' => 'Ömnenne',
	'lqt_move_noreason' => 'Keine Aanlass aanjejovve.',
	'lqt_delete_deleting_allreplies' => 'all Antwoote',
);

/** Luxembourgish (Lëtzebuergesch)
 * @author Robby
 */
$messages['lb'] = array(
	'lqt_newmessages' => 'Nei Messagen',
	'lqt_browse_archive_with_recent' => 'méi al',
	'lqt_new_thread' => 'Eng nei Diskussioun ufänken',
	'lqt_edited_notice' => 'Verännert',
	'lqt_reply' => 'Äntwert',
	'lqt_delete' => 'Läschen',
	'lqt_undelete' => 'Restauréieren',
	'lqt_permalink' => 'Permanente Link',
	'lqt_discussion_link' => 'Diskussioun',
	'lqt_from_talk' => 'vun $1',
	'lqt_newer' => '«méi nei',
	'lqt_older' => 'méi al»',
	'lqt_hist_comment_edited' => 'Bemierkung geännert',
	'lqt_hist_summary_changed' => 'Résumé geännert',
	'lqt_hist_deleted' => 'Geläscht',
	'lqt_hist_undeleted' => 'Restauréiert',
	'lqt_hist_moved_talkpage' => 'Geréckelt',
	'lqt_hist_tooltip_older_disabled' => 'Dës Link ass ausgeschalt well Dir op der läschter Säit sidd',
	'lqt_revision_as_of' => 'Versioun vum $1.',
	'lqt_youhavenewmessages' => 'Dir hutt [$1 nei Messagen]',
	'lqt_changes_from' => ' vun',
	'lqt_changes_summary_of' => ' vun',
	'lqt_protectedfromreply_link' => 'protegéiert',
	'lqt_subject' => 'Sujet:',
	'lqt_nosubject' => '"kee Sujet"',
	'lqt_noreason' => 'Kee Grond uginn.',
	'lqt_thread_deleted_for_sysops_deleted' => 'geläscht',
	'lqt_summary_notice_link' => 'Gitt e Résumé un',
	'lqt_summary_subtitle' => 'De Résumé vun $1',
	'lqt_move_torename_edit' => 'et änneren',
	'lqt_move_move' => 'Réckelen',
	'lqt_move_noreason' => 'Kee Grond uginn',
	'lqt_delete_undeleting' => '$1 gëtt geläscht.',
	'lqt_delete_deleting_allreplies' => 'all Äntwerten',
	'lqt_delete_return' => 'Zréck op $1',
	'lqt_delete_return_link' => "d'Diskussiounssäit",
	'lqt_header_warning_big' => 'Dir ännert een/eng $1',
	'lqt_header_warning_bold' => 'Iwwerschrëft vun enger Diskussiounssäit',
	'lqt_header_warning_new_discussion' => 'eng nei Diskussioun ufänken.',
	'lqt_remember_sort' => 'Dës Astellung verhalen',
	'lqt-any-date' => 'All Datum',
	'lqt-only-date' => 'Nëmmen dës Datumen:',
	'lqt-date-from' => 'Vum',
	'lqt-date-to' => 'Bis',
	'lqt-title' => 'Titel',
	'lqt-summary' => 'Resumé',
	'lqt-older' => 'méi al',
	'lqt-newer' => 'méi nei',
	'lqt-read-message' => 'Geliest',
	'lqt-email-undo' => 'Zréck setzen',
	'lqt-messages-sent' => 'Messagen déi dir geschéckt kritt hutt:',
	'lqt-other-messages' => 'Messagen op aneren Diskussiounssäiten:',
	'lqt-new-messages' => 'Dir hutt nei Messagen.',
);

/** Lozi (Silozi)
 * @author Ooswesthoesbes
 */
$messages['loz'] = array(
	'lqt_newmessages' => 'Nca Mulumiwa',
	'lqt_revision_as_of' => 'Selt di $1.',
	'lqt_youhavenewmessages' => 'A sa $1.',
	'lqt_changes_from' => ' di',
	'lqt_protectedfromreply_link' => 'sa bukelezi',
	'lqt_thread_deleted_for_sysops_deleted' => 'sa afi kulobala',
);

/** Lithuanian (Lietuvių)
 * @author Tomasdd
 */
$messages['lt'] = array(
	'lqt_delete' => 'Ištrinti',
);

/** Malayalam (മലയാളം)
 * @author Shijualex
 */
$messages['ml'] = array(
	'lqt_newmessages' => 'പുതിയ സന്ദേശങ്ങള്‍',
	'lqt_movethread' => 'ത്രെഡ് മറ്റൊരു താളിലേക്കു മാറ്റുക',
	'lqt_browse_archive_with_recent' => 'പഴയത്',
	'lqt_contents_title' => 'ഉള്ളടക്കം:',
	'lqt_new_thread' => 'പുതിയൊരു സം‌വാദം ആരംഭിക്കുക',
	'lqt_edited_notice' => 'തിരുത്തി',
	'lqt_reply' => 'മറുപടി',
	'lqt_delete' => 'മായ്ക്കുക',
	'lqt_undelete' => 'പുനഃസ്ഥാപിക്കുക',
	'lqt_permalink' => 'സ്ഥിരംകണ്ണി',
	'lqt_discussion_link' => 'സം‌വാദം',
	'lqt_from_talk' => '$1ല്‍ നിന്ന്',
	'lqt_newer' => '«പുതിയത്',
	'lqt_older' => 'പഴയത്»',
	'lqt_hist_summary_changed' => 'ചുരുക്കം മാറ്റി',
	'lqt_hist_reply_created' => 'പുതിയ മറുപടി ഉണ്ടാക്കി',
	'lqt_hist_deleted' => 'മായ്ച്ചു',
	'lqt_hist_moved_talkpage' => 'തലക്കെട്ട് മാറ്റി',
	'lqt_hist_tooltip_newer_disabled' => 'നിങ്ങള്‍ ആദ്യത്തെ താളിലായതിനാല്‍ ഈ കണ്ണി നിര്‍‌വീര്യമാക്കി.',
	'lqt_hist_tooltip_older_disabled' => 'നിങ്ങള്‍ അവസാനത്തെ താളിലായതിനാല്‍ ഈ കണ്ണി നിര്‍‌വീര്യമാക്കി.',
	'lqt_revision_as_of' => '$1ല്‍ ഉണ്ടായിരുന്ന പതിപ്പ്',
	'lqt_change_reply_created' => 'അടയാളപ്പെടുത്തി കാണിച്ചിരിക്കുന്ന അഭിപ്രായം ഈ പതിപ്പില്‍ ചേര്‍ക്കപ്പെട്ടതാണ്‌.',
	'lqt_change_edited_root' => 'അടയാളപ്പെടുത്തി കാണിച്ചിരിക്കുന്ന അഭിപ്രായം ഈ പതിപ്പില്‍ തിരുത്തപ്പെട്ടിട്ടുണ്ട്.',
	'lqt_youhavenewmessages' => 'നിങ്ങള്‍ക്ക് $1 ഉണ്ട്.',
	'lqt_protectedfromreply_link' => 'സം‌രക്ഷിക്കപ്പെട്ടിരിക്കുന്നു',
	'lqt_subject' => 'വിഷയം:',
	'lqt_nosubject' => '«വിഷയം കൊടുത്തിട്ടില്ല»',
	'lqt_noreason' => 'കാരണമൊന്നും സൂചിപ്പിച്ചിട്ടില്ല',
	'lqt_thread_deleted_for_sysops' => 'ഈ ത്രെഡ് $1. സിസോപ്പുകള്‍ക്ക് മാത്രമേ ഇതു ദൃശ്യമാവൂ.',
	'lqt_thread_deleted_for_sysops_deleted' => 'മായ്ച്ചു',
	'lqt_thread_deleted' => 'ഈ ത്രെഡ് ഒഴിവാക്കി.',
	'lqt_summary_notice_link' => 'സംഗ്രഹം എഴുതുക',
	'lqt_summary_label' => 'ഈ ത്രെഡ് താഴെ പ്രദര്‍ശിപ്പിച്ചിരിക്കുന്ന പോലെ സംഗ്രഹിച്ചിരിക്കുന്നു.',
	'lqt_summary_subtitle' => '$1ന്റെ സംഗ്രഹം.',
	'lqt_nosuchrevision' => 'ഈ ത്രെഡിനു അങ്ങനെയൊരു പതിപ്പില്ല.',
	'lqt_threadrequired' => 'URL-ല്‍ ഒരു ത്രെഡ് ചേര്‍ക്കണം',
	'lqt_move_movingthread' => '$1 മാറ്റുന്നു. ഈ ത്രെഡ് $2ന്റെ ഭാഗമാണ്‌.',
	'lqt_move_torename' => "ഈ ത്രെഡ് പുനര്‍‌നാമകരണം ചെയ്യാന്‍, $1 ചെയ്തു 'തലക്കുറി' മാറ്റുക",
	'lqt_move_torename_edit' => 'തിരുത്തുക',
	'lqt_move_destinationtitle' => 'ലക്ഷ്യ സം‌വാദം താളിന്റെ തലക്കെട്ട്:',
	'lqt_move_move' => 'തലക്കെട്ടു മാറ്റുക',
	'lqt_move_nodestination' => 'ഒരു ലക്ഷ്യം നിര്‍ബന്ധമായും ചേര്‍ത്തിരിക്കണം.',
	'lqt_move_noreason' => 'കാരണമൊന്നും സൂചിപ്പിച്ചിട്ടില്ല',
	'lqt_move_success' => 'ത്രെഡ് $1ലേക്കു മാറ്റി.',
	'lqt_delete_undeleting' => '$1നെ പുനഃസ്ഥാപിക്കുന്നു.',
	'lqt_delete_deleting_allreplies' => 'എല്ലാ മറുപടികളും',
	'lqt_delete_deleted' => 'ത്രെഡ് മായ്ക്കപ്പെട്ടിരിക്കുന്നു.',
	'lqt_delete_return' => '$1 എന്ന താളിലേക്ക് തിരിച്ചുപോവുക.',
	'lqt_delete_return_link' => 'സം‌വാദം താള്‍',
	'lqt_header_warning_big' => 'താങ്കള്‍ തിരുത്തി കൊണ്ടിരിക്കുന്നത് ഒരു $1 ആണ്‌.',
	'lqt_header_warning_new_discussion' => 'പുതിയൊരു സം‌വാദം ആരംഭിക്കുക',
	'lqt_sorting_order' => 'സോര്‍ട്ടിങ്ങ് ക്രമം:',
	'lqt_remember_sort' => 'ഈ ക്രമീകരണം ഓര്‍ത്തു വെക്കുക',
	'lqt_sort_newest_changes' => 'അവസാനം തിരുത്തിയത് ആദ്യം പ്രദര്‍ശിപ്പിക്കുക',
	'lqt_sort_newest_threads' => 'ഏറ്റവും പുതിയ ത്രെഡുകള്‍ ആദ്യം',
	'lqt_sort_oldest_threads' => 'ഏറ്റവും പഴയ ത്രെഡുകള്‍ ആദ്യം',
);

/** Marathi (मराठी)
 * @author Kaustubh
 * @author Mahitgar
 */
$messages['mr'] = array(
	'lqt-desc' => 'चर्चा पानावर चर्चेचे मुद्दे वाढवा',
	'lqt-nothread' => 'या आर्चिव्ह मध्ये थ्रेड्स नाहीत.',
	'lqt_newmessages' => 'नवीन संदेश',
	'lqt_movethread' => 'थ्रेड दुसर्‍या पानावर हलवा',
	'lqt_deletethread' => 'थ्रेड वगळा किंवा पुनर्स्थापित करा',
	'lqt_browse_archive_without_recent' => 'आर्चिव्ह केलेले थ्रेड्स पहा',
	'lqt_browse_archive_with_recent' => 'जुने',
	'lqt_recently_archived' => 'अलीकडील काळात आर्चिव्ह केलेले:',
	'lqt_contents_title' => 'कंटेन्ट्स:',
	'lqt_add_header' => 'हेडर लिहा',
	'lqt_new_thread' => 'नवीन चर्चा चालू करा',
	'lqt_in_response_to' => '$2 च्या $1 ला उत्तर, वरील:',
	'lqt_edited_notice' => 'संपादित',
	'lqt_move_placeholder' => 'हा थ्रेड $1 थ्रेडची जागा दर्शवितो, जो दुसर्‍या चर्चा पानावर हलविण्यात आलेला होता. हे स्थानांतरण $2 ने $3 ला केले.',
	'lqt_reply' => 'उत्तर',
	'lqt_delete' => 'वगळा',
	'lqt_undelete' => 'पुनर्स्थापित करा',
	'lqt_permalink' => 'शाश्वत दुवा',
	'lqt_fragment' => '$2 कडून $1 चा एक हिस्सा',
	'lqt_discussion_link' => 'चर्चा',
	'lqt_from_talk' => '$1 पासून',
	'lqt_newer' => '«नवीन',
	'lqt_older' => 'जुने»',
	'lqt_hist_comment_edited' => 'शेरा संपादला',
	'lqt_hist_summary_changed' => 'संक्षिप्त माहिती बदलली',
	'lqt_hist_reply_created' => 'नवीन उत्तर तयार केले',
	'lqt_hist_thread_created' => 'नवीन थ्रेड बनविला',
	'lqt_hist_deleted' => 'वगळलेले',
	'lqt_hist_undeleted' => 'पुनर्स्थापित केले',
	'lqt_hist_moved_talkpage' => 'स्थानांतरीत',
	'lqt_hist_listing_subtitle' => 'इतिहास नोंद पाहत आहे.',
	'lqt_hist_view_whole_thread' => 'संपूर्ण थ्रेडसाठीचा इतिहास पहा',
	'lqt_hist_no_revisions_error' => 'या थ्रेडला इतिहास नाही. हे आश्चर्यकारक आहे.',
	'lqt_hist_past_last_page_error' => 'तुम्ही अस्तित्वात असणार्‍या इतिहास पानांच्या संख्येच्या पलीकडे गेलेला आहात.',
	'lqt_hist_tooltip_newer_disabled' => 'तुम्ही पहिल्या पानावर असल्याने हा दुवा वापरू शकत नाही.',
	'lqt_hist_tooltip_older_disabled' => 'तुम्ही शेवटच्या पानावर असल्याने हा दुवा वापरू शकत नाही.',
	'lqt_revision_as_of' => '$1 ची आवृत्ती.',
	'lqt_change_new_thread' => 'ही या थ्रेडची सुरुवातीची आवृत्ती आहे.',
	'lqt_change_reply_created' => 'रंगविलेली (highlighted) सूचना या आवृत्तीमध्ये देण्यात आली होती.',
	'lqt_change_edited_root' => 'रंगविलेली सूचना या आवृत्तीमध्ये संपादित करण्यात आली होती.',
	'lqt_youhavenewmessages' => 'तुमच्याकडे $1 आहेत.',
	'lqt_changes_from' => 'कडून',
	'lqt_changes_summary_of' => 'चे',
	'lqt_protectedfromreply' => 'ह्या थ्रेडला उत्तर देणे $1 आहे.',
	'lqt_protectedfromreply_link' => 'सुरक्षीत',
	'lqt_subject' => 'विषय:',
	'lqt_nosubject' => '«विषय नाही»',
	'lqt_noreason' => 'कारण दिलेले नाही.',
	'lqt_thread_deleted_for_sysops' => 'हा थ्रेड $1 आहे व फक्त प्रबंधकांनाच दिसू शकतो.',
	'lqt_thread_deleted_for_sysops_deleted' => 'वगळला',
	'lqt_thread_deleted' => 'हा थ्रेड वगळला आहे.',
	'lqt_summary_notice' => 'मागील $2 दिवसांमध्ये या चर्चेमध्ये काहीही बदल झालेले नाहीत. जर ती पूर्ण झालेली असेल, तर तुम्ही ती $1 करू शकता.',
	'lqt_summary_notice_link' => 'संक्षिप्त माहिती लिहा',
	'lqt_summary_label' => 'ह्या थ्रेडला खालील संक्षिप्त माहिती दिलेली आहे:',
	'lqt_summary_subtitle' => '$1 ची संक्षिप्त माहिती.',
	'lqt_nosuchrevision' => 'या थ्रेडची अशी आवृत्ती नाही.',
	'lqt_nosuchthread' => 'असा थ्रेड नाही.',
	'lqt_threadrequired' => 'URL मध्ये थ्रेड देणे आवश्यक आहे.',
	'lqt_move_movingthread' => '$1 चे स्थानांतरण चालू आहे. हा $2 चाच भाग आहे.',
	'lqt_move_torename' => "ह्या थ्रेड चे नाव बदलण्यासाठी, $1 आणि 'विषय' बदला.",
	'lqt_move_torename_edit' => 'संपादन करा',
	'lqt_move_destinationtitle' => 'लक्ष्य चर्चा पानाचे शीर्षक:',
	'lqt_move_move' => 'हलवा',
	'lqt_move_nodestination' => 'तुम्ही लक्ष्य देणे आवश्यक आहे.',
	'lqt_move_noreason' => 'कारण दिलेले नाही.',
	'lqt_move_success' => 'हा थ्रेड $1 वर हलविण्यात आलेला आहे.',
	'lqt_delete_undeleting' => '$1 ला पुनर्स्थापित करत आहे.',
	'lqt_delete_undeletethread' => 'थ्रेड पुनर्स्थापित करा',
	'lqt_delete_partof' => 'हा थ्रेड $1 चा भाग आहे.',
	'lqt_delete_deleting' => '$1 वगळीत आहे व त्यामध्ये $2 करीत आहे.',
	'lqt_delete_deleting_allreplies' => 'सर्व उत्तरे',
	'lqt_delete_deletethread' => 'थ्रेड व उत्तरे वगळा',
	'lqt_delete_deleted' => 'थ्रेड वगळण्यात आलेला आहे.',
	'lqt_delete_undeleted' => 'थ्रेड पुनर्स्थापित करण्यात आलेला आहे.',
	'lqt_delete_return' => '$1 कडे परत जा.',
	'lqt_delete_return_link' => 'चर्चा पान',
	'lqt_delete_unallowed' => 'तुम्हाला थ्रेड वगळण्याची परवानगी नाही.',
	'lqt_talkpage_autocreate_summary' => 'पहिला थ्रेड लिहिल्यानंतर चर्चा पान आपोआप तयार झाले होते.',
	'lqt_header_warning_big' => 'तुम्ही एक $1 संपादित आहात.',
	'lqt_header_warning_after_big' => 'हेडर फक्त घोषणा व प्रिफेस करीता वापरावेत. तुम्ही त्याऐवजी $2 वापरू शकता.',
	'lqt_header_warning_bold' => 'चर्चा पानाचे हेडर',
	'lqt_header_warning_new_discussion' => 'नवीन चर्चा सुरु करा',
	'lqt_sorting_order' => 'अनुक्रम ठरवा:',
	'lqt_remember_sort' => 'ह्या पसंती लक्षात ठेवा',
	'lqt_sort_newest_changes' => 'शेवटी बदललेले पहिल्यांदा',
	'lqt_sort_newest_threads' => 'नवीन थ्रेड पहिल्यांदा',
	'lqt_sort_oldest_threads' => 'जुने थ्रेड पहिल्यांदा',
);

/** Maltese (Malti)
 * @author Roderick Mallia
 */
$messages['mt'] = array(
	'lqt_move_move' => 'Mexxi',
);

/** Nahuatl (Nāhuatl)
 * @author Fluence
 */
$messages['nah'] = array(
	'lqt_newmessages' => 'Yancuīc tlahcuilōltzintli',
	'lqt_delete' => 'Ticpolōz',
	'lqt_hist_deleted' => 'Ōmopolo',
	'lqt_protectedfromreply_link' => 'ōmoquīxti',
	'lqt_noreason' => 'Ahmo cah īxtlamatiliztli.',
	'lqt_thread_deleted_for_sysops_deleted' => 'ōmopolo',
	'lqt_move_move' => 'Ticzacāz',
	'lqt_move_noreason' => 'Ahmo cah īxtlamatiliztli.',
	'lqt_delete_return' => 'Timocuepāz īhuīc $1.',
);

/** Nedersaksisch (Nedersaksisch)
 * @author Servien
 */
$messages['nds-nl'] = array(
	'lqt_move_move' => 'Herneum',
);

/** Dutch (Nederlands)
 * @author Annabel
 * @author SPQRobin
 * @author Siebrand
 */
$messages['nl'] = array(
	'lqt-desc' => "Voegt overleg in threads op overlegpagina's toe",
	'lqt-nothread' => 'Er zijn geen threads in het archief aanwezig.',
	'lqt_newmessages' => 'Nieuwe berichten',
	'lqt_movethread' => 'Onderwerpspagina naar andere pagina hernoemen',
	'lqt_deletethread' => 'Onderwerpspagina verwijderen of terugplaatsen',
	'lqt_browse_archive_without_recent' => "Gearchiveerde onderwerpspagina's bekijken",
	'lqt_browse_archive_with_recent' => 'ouder',
	'lqt_recently_archived' => 'Recent gearchiveerd:',
	'lqt_contents_title' => 'Inhoud:',
	'lqt_add_header' => 'Kopje toevoegen',
	'lqt_new_thread' => 'Nieuw onderwerp starten',
	'lqt_in_response_to' => 'In antwoord aan $1 door $2, boven:',
	'lqt_edited_notice' => 'Bewerkt',
	'lqt_move_placeholder' => 'Deze onderwerpspagina is een markering die aanduidt dat een onderwerpspagina, $1, verwijderd is van deze pagina naar een andere overlegpagina. Deze hernoeming is gedaan door $2 op $3.',
	'lqt_reply' => 'Antwoorden',
	'lqt_delete' => 'Verwijderen',
	'lqt_undelete' => 'Terugplaatsen',
	'lqt_permalink' => 'Permanente link',
	'lqt_fragment' => 'een fragment van een $1 van $2',
	'lqt_discussion_link' => 'overleg',
	'lqt_from_talk' => 'van $1',
	'lqt_newer' => '«nieuwer',
	'lqt_older' => 'ouder»',
	'lqt_hist_comment_edited' => 'Tekst opmerking bewerkt',
	'lqt_hist_summary_changed' => 'Samenvatting aangepast',
	'lqt_hist_reply_created' => 'Nieuw antwoord gemaakt',
	'lqt_hist_thread_created' => 'Nieuwe onderwerpspagina gemaakt',
	'lqt_hist_deleted' => 'Verwijderd',
	'lqt_hist_undeleted' => 'Teruggeplaatst',
	'lqt_hist_moved_talkpage' => 'Verplaatst',
	'lqt_hist_listing_subtitle' => 'U bent een oudere versie aan het bekijken.',
	'lqt_hist_view_whole_thread' => 'Geschiedenis van de hele onderwerpspagina bekijken',
	'lqt_hist_no_revisions_error' => 'Deze onderwerpspagina heeft geen oudere versies. Dat is nogal vreemd.',
	'lqt_hist_past_last_page_error' => 'U hebt een hoger paginanummer gekozen dan bestaat in de geschiedenis.',
	'lqt_hist_tooltip_newer_disabled' => 'Deze link is niet actief omdat u op de eerste pagina bent.',
	'lqt_hist_tooltip_older_disabled' => 'Deze link is niet actief omdat u op de laatste pagina bent.',
	'lqt_revision_as_of' => 'Versie op $1.',
	'lqt_change_new_thread' => 'Dit is de eerste versie van de onderwerpspagina.',
	'lqt_change_reply_created' => 'De gemarkeerde opmerking is in deze versie toegevoegd.',
	'lqt_change_edited_root' => 'De gemarkeerde opmerking is in deze versie bewerkt.',
	'lqt_youhavenewmessages' => 'U hebt [$1 nieuwe berichten].',
	'lqt_changes_from' => ' van',
	'lqt_changes_summary_of' => ' van',
	'lqt_protectedfromreply' => 'Deze onderwerpspagina is $1 van te worden beantwoord.',
	'lqt_protectedfromreply_link' => 'beveiligd',
	'lqt_subject' => 'Onderwerp:',
	'lqt_nosubject' => '«geen onderwerp»',
	'lqt_noreason' => 'Geen reden gegeven.',
	'lqt_thread_deleted_for_sysops' => 'Deze onderwerpspagina is $1 en is alleen zichtbaar voor beheerders.',
	'lqt_thread_deleted_for_sysops_deleted' => 'verwijderd',
	'lqt_thread_deleted' => 'Deze onderwerpspagina is verwijderd.',
	'lqt_summary_notice' => 'Er zijn geen wijzigingen geweest in de afgelopen $2 dagen. Als het overleg is afgerond, wordt u aangemoedigd om $1.',
	'lqt_summary_notice_link' => 'een samenvatting te schrijven',
	'lqt_summary_label' => 'Deze onderwerpspagina werd samengevat als volgt:',
	'lqt_summary_subtitle' => 'de samenvatting van $1',
	'lqt_nosuchrevision' => 'Er bestaat geen dergelijke versie van deze onderwerpspagina.',
	'lqt_nosuchthread' => 'Er bestaat geen dergelijke onderwerpspagina.',
	'lqt_threadrequired' => 'U moet een onderwerspagina opgeven in de URL.',
	'lqt_move_movingthread' => 'Hernoemen van $1. Deze onderwerpspagina is een deel van $2.',
	'lqt_move_torename' => "Om deze onderwerpspagina te hernoemen, $1 en wijzig het 'Onderwerp'-veld.",
	'lqt_move_torename_edit' => 'bewerk het',
	'lqt_move_destinationtitle' => 'Bestemmingsoverlegpagina:',
	'lqt_move_move' => 'Hernoemen',
	'lqt_move_nodestination' => 'U moet een bestemming opgeven.',
	'lqt_move_noreason' => 'Geen reden gegeven.',
	'lqt_move_success' => 'De onderwerpspagina is hernoemd naar $1.',
	'lqt_delete_undeleting' => 'Terugplaatsen van $1.',
	'lqt_delete_undeletethread' => 'Onderwerpspagina terugplaatsen',
	'lqt_delete_partof' => 'Deze onderwerpspagina is een deel van $1.',
	'lqt_delete_deleting' => 'Verwijderen van $1 en $2 ernaar.',
	'lqt_delete_deleting_allreplies' => 'alle antwoorden',
	'lqt_delete_deletethread' => 'Onderwerpspagina verwijderen',
	'lqt_delete_deleted' => 'De onderwerpspagina is verwijderd.',
	'lqt_delete_undeleted' => 'De onderwerpspagina is teruggeplaatst.',
	'lqt_delete_return' => 'Terugkeren naar $1.',
	'lqt_delete_return_link' => 'de overlegpagina',
	'lqt_delete_unallowed' => "U mag geen onderwerpspagina's verwijderen.",
	'lqt_talkpage_autocreate_summary' => 'Overlegpagina automatisch gemaakt wanneer eerste onderwerpspagina is gemaakt.',
	'lqt_header_warning_big' => 'U bent een $1 aan het bewerken.',
	'lqt_header_warning_after_big' => 'Koppen zijn voor aankondigingen en inleidingen. Wellicht kunt u beter gebruik maken van $2.',
	'lqt_header_warning_bold' => 'koptekst overlegpagina',
	'lqt_header_warning_new_discussion' => 'begin een nieuw overleg',
	'lqt_sorting_order' => 'Sorteervolgorde:',
	'lqt_remember_sort' => 'Deze instelling onthouden',
	'lqt_sort_newest_changes' => 'laatst gewijzigd bovenaan',
	'lqt_sort_newest_threads' => 'nieuwste threads bovenaan',
	'lqt_sort_oldest_threads' => 'oudste threads bovenaan',
	'lqt-any-date' => 'Elke datum',
	'lqt-only-date' => 'Alleen deze data:',
	'lqt-date-from' => 'Van',
	'lqt-date-to' => 'Tot',
	'lqt-title' => 'Naam',
	'lqt-summary' => 'Samenvatting',
	'lqt-older' => 'ouder',
	'lqt-newer' => 'nieuwer',
	'lqt-searching' => 'Bezig met zoeken naar threads...',
	'lqt-read-message' => 'Lezen',
	'lqt-read-message-tooltip' => 'Deze thread verwijderen uit nieuwe berichten.',
	'lqt-marked-read' => "Thread '''$1''' is gemarkeerd als gelezen.",
	'lqt-count-marked-read' => '$1 {{PLURAL:$1|bericht|berichten}} gemarkeerd als gelezen.',
	'lqt-email-undo' => 'Ongedaan maken',
	'lqt-messages-sent' => 'Aan u verzonden berichten:',
	'lqt-other-messages' => "Berichten op andere overlegpagina's:",
	'lqt-new-messages' => 'U hebt nieuwe berichten.',
	'lqt-email-info-undo' => 'Terug naar de thread die u zojuist hebt verlaten.',
	'lqt-date-info' => 'Deze verwijzing is niet actief omdat u threads van alle data bekijkt.',
);

/** Norwegian Nynorsk (‪Norsk (nynorsk)‬)
 * @author Jon Harald Søby
 */
$messages['nn'] = array(
	'lqt_browse_archive_with_recent' => 'eldre',
	'lqt_delete' => 'Slett',
	'lqt_discussion_link' => 'diskusjon',
	'lqt_older' => 'eldre »',
	'lqt_hist_deleted' => 'Sletta',
	'lqt_subject' => 'Emne:',
	'lqt_noreason' => 'Inga grunngjeving.',
	'lqt_thread_deleted_for_sysops_deleted' => 'sletta',
	'lqt_move_noreason' => 'Inga grunngjeving.',
	'lqt_delete_return' => 'Attende til $1.',
);

/** Norwegian (bokmål)‬ (‪Norsk (bokmål)‬)
 * @author Jon Harald Søby
 */
$messages['no'] = array(
	'lqt-desc' => 'Legger til trådede diskusjoner til diskusjonssider',
	'lqt-nothread' => 'Det er ingen tråder i arkivet.',
	'lqt_newmessages' => 'Nye beskjeder',
	'lqt_movethread' => 'Flytt tråd til en annen side',
	'lqt_deletethread' => 'Slett eller gjenopprett tråd',
	'lqt_browse_archive_without_recent' => 'Vis arkiverte tråder',
	'lqt_browse_archive_with_recent' => 'eldre',
	'lqt_recently_archived' => 'Nylig arkivert',
	'lqt_contents_title' => 'Innhold:',
	'lqt_add_header' => 'Legg til hodeseksjon',
	'lqt_new_thread' => 'Start en ny diskusjon',
	'lqt_in_response_to' => 'Som svar til $1 av $2, over:',
	'lqt_edited_notice' => 'Redigert',
	'lqt_move_placeholder' => 'Denne tråden eksisterer kun for å vise at tråden $1 ble flyttet fra denne siden til en annen diskusjonsside. Denne flyttingen ble foretatt av $2 $3.',
	'lqt_reply' => 'Svar',
	'lqt_delete' => 'Slett',
	'lqt_undelete' => 'Gjenopprett',
	'lqt_permalink' => 'Permanent lenke',
	'lqt_fragment' => 'et fragment av $1 fra $2',
	'lqt_discussion_link' => 'diskusjon',
	'lqt_from_talk' => 'fra $1',
	'lqt_newer' => '« nyere',
	'lqt_older' => 'eldre »',
	'lqt_hist_comment_edited' => 'Kommentartekst redigert',
	'lqt_hist_summary_changed' => 'Sammendrag endret',
	'lqt_hist_reply_created' => 'Nytt svar opprettet',
	'lqt_hist_thread_created' => 'Ny tråd opprettet',
	'lqt_hist_deleted' => 'Slettet',
	'lqt_hist_undeleted' => 'Gjenopprettet',
	'lqt_hist_moved_talkpage' => 'Flyttet',
	'lqt_hist_listing_subtitle' => 'Ser på en historikkliste.',
	'lqt_hist_view_whole_thread' => 'Se historikken for hele tråden',
	'lqt_hist_no_revisions_error' => 'Denne tråden har ingen revisjoner. Det er ganske rart.',
	'lqt_hist_past_last_page_error' => 'Det finnes ingen flere sider med historikk.',
	'lqt_hist_tooltip_newer_disabled' => 'Denne lenken er slått av fordi du er på den første siden.',
	'lqt_hist_tooltip_older_disabled' => 'Denne lenken er slått av fordi du er på den siste siden.',
	'lqt_revision_as_of' => 'Revisjon à $1.',
	'lqt_change_new_thread' => 'Dette er trådens første revisjon.',
	'lqt_change_reply_created' => 'Den markerte kommentaren ble opprettet i denne revisjonen.',
	'lqt_change_edited_root' => 'Den markerte kommentaren ble redigert i denne revisjonen.',
	'lqt_youhavenewmessages' => 'Du har [$1 nye beskjeder].',
	'lqt_changes_from' => ' fra',
	'lqt_changes_summary_of' => '  av',
	'lqt_protectedfromreply' => 'Denne tråden har blitt $1 for svar.',
	'lqt_protectedfromreply_link' => 'låst',
	'lqt_subject' => 'Emne:',
	'lqt_nosubject' => '«intet emne»',
	'lqt_noreason' => 'Ingen grunn gitt.',
	'lqt_thread_deleted_for_sysops' => 'Denne tråden har blitt $1 og er kun synlig for administratorer.',
	'lqt_thread_deleted_for_sysops_deleted' => 'slettet',
	'lqt_thread_deleted' => 'Denne tråden har blitt slettet.',
	'lqt_summary_notice' => 'Det har ikke vært noen endringer på denne diskusjonssiden på minst $2 dager. Om diskusjonen er ferdig, vil du muligens $1.',
	'lqt_summary_notice_link' => 'skrive et sammendrag',
	'lqt_summary_label' => 'Denne tråden har fått følgende sammendrag:',
	'lqt_summary_subtitle' => 'sammendraget av $1.',
	'lqt_nosuchrevision' => 'Det er ingen slik revisjon av denne tråden.',
	'lqt_nosuchthread' => 'Det er ingen slik tråd.',
	'lqt_threadrequired' => 'Du må angi en tråd i URL-en.',
	'lqt_move_movingthread' => 'Flytter $1. Denne tråden er del av $2.',
	'lqt_move_torename' => 'For å omdøpe denne tråden, $1 og endre «Emne»-feltet.',
	'lqt_move_torename_edit' => 'rediger den',
	'lqt_move_destinationtitle' => 'Tittel på måldiskusjonsside:',
	'lqt_move_move' => 'Flytt',
	'lqt_move_nodestination' => 'Du må angi et mål.',
	'lqt_move_noreason' => 'Ingen grunn gitt.',
	'lqt_move_success' => 'Denne tråden ble flyttet til $1.',
	'lqt_delete_undeleting' => 'Gjenoppretter $1.',
	'lqt_delete_undeletethread' => 'Gjenopprett tråd',
	'lqt_delete_partof' => 'Denne tråden er del av $1.',
	'lqt_delete_deleting' => 'Sletter $1 og $2 dit.',
	'lqt_delete_deleting_allreplies' => 'alle svar',
	'lqt_delete_deletethread' => 'Slett tråd og svar',
	'lqt_delete_deleted' => 'Tråden ble slettet.',
	'lqt_delete_undeleted' => 'Tråden ble gjenopprettet.',
	'lqt_delete_return' => 'Tilbake til $1.',
	'lqt_delete_return_link' => 'diskusjonssiden',
	'lqt_delete_unallowed' => 'Du kan ikke slette tråder.',
	'lqt_talkpage_autocreate_summary' => 'Diskusjonsside opprettet automatisk da første tråd ble postet.',
	'lqt_header_warning_big' => 'Du redigerer en $1.',
	'lqt_header_warning_after_big' => 'Hodeseksjoner er for annonseringer og innledninger. Du vil muligens i stedet $2.',
	'lqt_header_warning_bold' => 'diskusjonssideinnledning',
	'lqt_header_warning_new_discussion' => 'start en ny diskusjon',
	'lqt_sorting_order' => 'Sorteringsrekkefølge:',
	'lqt_remember_sort' => 'Husk denne preferansen',
	'lqt_sort_newest_changes' => 'de sist endrede først',
	'lqt_sort_newest_threads' => 'nyeste tråder først',
	'lqt_sort_oldest_threads' => 'eldste tråder først',
	'lqt-any-date' => 'Når som helst',
	'lqt-only-date' => 'Kun disse datoene:',
	'lqt-date-from' => 'Fra',
	'lqt-date-to' => 'Til',
	'lqt-title' => 'Tittel',
	'lqt-summary' => 'Sammendrag',
	'lqt-older' => 'eldre',
	'lqt-newer' => 'nyere',
	'lqt-searching' => 'Søk etter tråder',
	'lqt-read-message' => 'Les',
	'lqt-read-message-tooltip' => 'Fjern denne tråden fra nye beskjeder.',
	'lqt-marked-read' => "Tråden '''$1''' markert lest.",
	'lqt-count-marked-read' => '{{PLURAL:$1|Én beskjed|$1 beskjeder}} markert lest.',
	'lqt-email-undo' => 'Angre',
	'lqt-messages-sent' => 'Beskjeder sendt til deg:',
	'lqt-other-messages' => 'Beskjeder på andre diskusjonssider:',
	'lqt-new-messages' => 'Du har nye beskjeder.',
	'lqt-email-info-undo' => 'Hent igjen tråden du nettopp fjernet.',
	'lqt-date-info' => 'Denne lenken er slått av fordi du ser tråder fra alle datoer.',
);

/** Northern Sotho (Sesotho sa Leboa)
 * @author Mohau
 */
$messages['nso'] = array(
	'lqt_delete' => 'Phumula',
	'lqt_youhavenewmessages' => 'O nale $1.',
	'lqt_changes_from' => 'gotšwa',
	'lqt_changes_summary_of' => 'ya',
	'lqt_subject' => 'Tabataba:',
	'lqt_nosubject' => '«gago tabataba»',
);

/** Occitan (Occitan)
 * @author Cedric31
 */
$messages['oc'] = array(
	'lqt-desc' => 'Apondís de fials de discussion dins las paginas de discussion',
	'lqt-nothread' => 'Existís pas cap de fial de discussion dins los archius.',
	'lqt_newmessages' => 'Messatges Novèls',
	'lqt_movethread' => 'Desplaçar lo fial vèrs una autra pagina',
	'lqt_deletethread' => 'Escafar o recuperar lo fial',
	'lqt_browse_archive_without_recent' => 'Afichar los subjèctes archivats',
	'lqt_browse_archive_with_recent' => 'mai ancians',
	'lqt_recently_archived' => 'Archivat recentament',
	'lqt_contents_title' => 'Taula dels subjèctes :',
	'lqt_add_header' => 'Apondre una entèsta',
	'lqt_new_thread' => 'Amodar una discussion novèla',
	'lqt_in_response_to' => 'En responsa a $1 per $2, mai naut :',
	'lqt_edited_notice' => 'Modificat',
	'lqt_move_placeholder' => "Aqueste fial es una marca substitutiva qu'indica qu'un fial, $1, es estat desplaçat d'aquesta pagina vèrs una autra pagina de discussion. Aqueste desplaçament es estat fach per $2 a $3.",
	'lqt_reply' => 'Respondre',
	'lqt_delete' => 'Escafar',
	'lqt_undelete' => 'Recuperar',
	'lqt_permalink' => 'Permaligam',
	'lqt_fragment' => 'un fragment de $1 a partir de $2',
	'lqt_discussion_link' => 'discussion',
	'lqt_from_talk' => 'de $1',
	'lqt_newer' => '«mai recent',
	'lqt_older' => 'mai ancian»',
	'lqt_hist_comment_edited' => 'Comentari modificat',
	'lqt_hist_summary_changed' => 'Somari modificat',
	'lqt_hist_reply_created' => 'Responsa novèla creada',
	'lqt_hist_thread_created' => 'Fial novèl creat',
	'lqt_hist_deleted' => 'Escafat',
	'lqt_hist_undeleted' => 'Recuperat',
	'lqt_hist_moved_talkpage' => 'Desplaçat',
	'lqt_hist_listing_subtitle' => 'Visualizar un istoric',
	'lqt_hist_view_whole_thread' => "Veire l'istoric de tot lo fial",
	'lqt_hist_no_revisions_error' => "Aqueste fial a pas d'istoric de revisions. Es curiós.",
	'lqt_hist_past_last_page_error' => "Avètz depassat lo nombre de paginas de l'istoric.",
	'lqt_hist_tooltip_newer_disabled' => 'Aqueste ligam es inactiu perque sètz sus la primièra pagina.',
	'lqt_hist_tooltip_older_disabled' => 'Aqueste ligam es inactiu perque sètz sus la darrièra pagina.',
	'lqt_revision_as_of' => 'Revision en data del $1',
	'lqt_change_new_thread' => 'Es la primièra revision del fial.',
	'lqt_change_reply_created' => 'Lo comentari en susbrilhança es estat creat dins aquesta revision.',
	'lqt_change_edited_root' => 'Lo comentari en susbrilhança es estat modificat dins aquesta revision.',
	'lqt_youhavenewmessages' => 'Avètz [$1 messatges novèls].',
	'lqt_changes_from' => 'de',
	'lqt_changes_summary_of' => ' de',
	'lqt_protectedfromreply' => 'Aqueste fial es estat $1. I podètz pas respondre.',
	'lqt_protectedfromreply_link' => 'protegit',
	'lqt_subject' => 'Subjècte :',
	'lqt_nosubject' => '« Cap de subjècte »',
	'lqt_noreason' => 'Cap de motiu balhat',
	'lqt_thread_deleted_for_sysops' => 'Aqueste fial es estat $1. Sols los administrators o pòdon veire.',
	'lqt_thread_deleted_for_sysops_deleted' => 'escafat',
	'lqt_thread_deleted' => 'Aqueste fial es estat escafat.',
	'lqt_summary_notice' => 'I a pas agut cap de cambiament dins aquesta discussion dempuèi almens $2 jorns. Se es estada concluïda, podètz aver besonh de $1.',
	'lqt_summary_notice_link' => 'escriure un resumit',
	'lqt_summary_label' => 'Aqueste fial es estat resumit per :',
	'lqt_summary_subtitle' => 'lo resumit de $1.',
	'lqt_nosuchrevision' => 'Cap de revision per aqueste fial correspon pas.',
	'lqt_nosuchthread' => 'Cap de fial correspon pas.',
	'lqt_threadrequired' => "Vos cal indicar un fial dins l'URL.",
	'lqt_move_movingthread' => '$1 en deplaçament. Aqueste fial fa partida de $2.',
	'lqt_move_torename' => "Per tornar nomenar aqueste fial, $1 e modificar lo camp ''Subjècte''.",
	'lqt_move_torename_edit' => 'lo modificar',
	'lqt_move_destinationtitle' => 'Títol de la pagina de discussion finala :',
	'lqt_move_move' => 'Desplaçar',
	'lqt_move_nodestination' => 'Vos cal indicar una destinacion.',
	'lqt_move_noreason' => 'Cap de rason balhada',
	'lqt_move_success' => 'Lo fial es estat desplaçat dins $1.',
	'lqt_delete_undeleting' => 'Recuperacion de $1',
	'lqt_delete_undeletethread' => 'Fial recuperat',
	'lqt_delete_partof' => 'Aqueste fial es una partida de $1.',
	'lqt_delete_deleting' => 'Supression del fial $1 e de $2.',
	'lqt_delete_deleting_allreplies' => 'totas las responsas',
	'lqt_delete_deletethread' => 'Escafar lo fial e respondre',
	'lqt_delete_deleted' => 'Lo fial es estat escafat.',
	'lqt_delete_undeleted' => 'Lo fial es estat recuperat.',
	'lqt_delete_return' => 'Tornar a $1',
	'lqt_delete_return_link' => 'la pagina de discussion',
	'lqt_delete_unallowed' => 'Avètz pas los dreches per escafar de fials.',
	'lqt_talkpage_autocreate_summary' => 'Pagina de discussion creada automaticament quand lo primièr fial de discussion es estat mandat.',
	'lqt_header_warning_big' => 'Modificatz un $1.',
	'lqt_header_warning_after_big' => 'Las entèstas son pels anoncis e las prefàcias. Deuriatz a la plaça $2.',
	'lqt_header_warning_bold' => "Entèsta d'una pagina de discussion",
	'lqt_header_warning_new_discussion' => 'amodar un fial de discussion novèl',
	'lqt_sorting_order' => 'Òrdre de triada :',
	'lqt_remember_sort' => 'Rapelar aquesta preferéncia',
	'lqt_sort_newest_changes' => 'darrièr modificat en primièr',
	'lqt_sort_newest_threads' => 'Los fials de discussion mai recents en primièr',
	'lqt_sort_oldest_threads' => 'Los fials de discussion mai ancians en primièr',
	'lqt-any-date' => 'Totas las datas',
	'lqt-only-date' => 'Unicament aquestas datas :',
	'lqt-date-from' => 'Del',
	'lqt-date-to' => 'A',
	'lqt-title' => 'Títol',
	'lqt-summary' => 'Resumit',
	'lqt-older' => 'mai ancian',
	'lqt-newer' => 'mai recent',
	'lqt-searching' => 'Recèrca dels fials de discussion',
	'lqt-read-message' => 'Legir',
	'lqt-read-message-tooltip' => 'Levar aqueste fial dels messatges novèls.',
	'lqt-marked-read' => "Fial de discussion '''$1''' marcat coma legit.",
	'lqt-count-marked-read' => '$1 {{PLURAL:$1|messatge marcat coma legit|messatges marcats coma legits}}',
	'lqt-email-undo' => 'Desfar',
	'lqt-messages-sent' => 'Messatges que vos son mandats :',
	'lqt-other-messages' => 'Messatges sus d’autras paginas de discussion :',
	'lqt-new-messages' => 'Avètz de messatges novèls.',
	'lqt-email-info-undo' => 'Restablir lo fial que venètz de levar.',
	'lqt-date-info' => 'Aqueste ligam es desactivat perque sètz a veire los fials a partir de totas las datas.',
);

/** Ossetic (Иронау)
 * @author Amikeco
 */
$messages['os'] = array(
	'lqt_delete' => 'Аппар',
	'lqt_protectedfromreply_link' => 'æхгæд',
);

/** Pampanga (Kapampangan)
 * @author Katimawan2005
 */
$messages['pam'] = array(
	'lqt-nothread' => 'Alang tema (thread) king simpanan (archive).',
	'lqt_newmessages' => 'Bayung mensahi',
	'lqt_movethread' => 'Iyalis ya ing tema (thread) king aliwang bulung',
	'lqt_deletethread' => 'Buran ya o iurung ya pangabura ing sinulad (thread)',
	'lqt_browse_archive_without_recent' => 'Lon la reng sinulad king simpanan (archived threads)',
	'lqt_browse_archive_with_recent' => 'minuna',
	'lqt_recently_archived' => 'Bayu-bayung mibili king simpanan (newly archived):',
	'lqt_contents_title' => 'Kalamnan:',
	'lqt_add_header' => 'Mangibiling pamagat (header)',
	'lqt_new_thread' => 'Magumpisang bayung discusiun/pamisabi-sabi',
	'lqt_in_response_to' => 'Pakibat nang $2 kang $1, king babo:',
	'lqt_edited_notice' => 'Me-edit',
	'lqt_reply' => 'Pakibat',
	'lqt_move_torename_edit' => 'i-edit ya',
	'lqt_delete_deleting_allreplies' => 'eganaganang pakibat',
	'lqt_delete_return' => 'Mibalik king $1.',
	'lqt_sorting_order' => 'Basi ning pamituki-tuki (sorting order)',
);

/** Polish (Polski)
 * @author Airwolf
 * @author Maikking
 * @author Masti
 * @author McMonster
 * @author Sp5uhe
 */
$messages['pl'] = array(
	'lqt_browse_archive_with_recent' => 'starszy',
	'lqt_contents_title' => 'Zawartość:',
	'lqt_add_header' => 'Dodaj nagłówek',
	'lqt_new_thread' => 'Rozpocznij nową dyskusję',
	'lqt_reply' => 'Odpowiedz',
	'lqt_delete' => 'Usuń',
	'lqt_undelete' => 'Odkasuj',
	'lqt_discussion_link' => 'dyskusja',
	'lqt_newer' => '«nowsze',
	'lqt_older' => 'starsze»',
	'lqt_hist_deleted' => 'Usunięto',
	'lqt_hist_undeleted' => 'Odtworzono',
	'lqt_hist_moved_talkpage' => 'Przeniesiony',
	'lqt_hist_tooltip_newer_disabled' => 'Link niedostępny gdyż jesteś na pierwszej stronie.',
	'lqt_hist_tooltip_older_disabled' => 'Link niedostępny gdyż jesteś na ostatniej stronie.',
	'lqt_youhavenewmessages' => 'Masz $1.',
	'lqt_changes_from' => 'z',
	'lqt_changes_summary_of' => 'z',
	'lqt_protectedfromreply_link' => 'zabezpieczony',
	'lqt_subject' => 'Temat:',
	'lqt_nosubject' => '«brak tematu»',
	'lqt_noreason' => 'Nie podano powodu',
	'lqt_thread_deleted_for_sysops' => 'Ten wątek został $1 i jest dostępny jedynie dla administratorów.',
	'lqt_thread_deleted_for_sysops_deleted' => 'usunięto',
	'lqt_nosuchthread' => 'Brak takiego wątku.',
	'lqt_move_torename_edit' => 'edytuj',
	'lqt_move_destinationtitle' => 'Nazwa docelowej strony dyskusji',
	'lqt_move_move' => 'Przenieś',
	'lqt_move_noreason' => 'Nie podano powodu.',
	'lqt_delete_undeleting' => 'Odtwarzanie $1.',
	'lqt_delete_return' => 'Wróć do $1.',
	'lqt_delete_return_link' => 'strona dyskusji',
);

/** Pashto (پښتو)
 * @author Ahmed-Najib-Biabani-Ibrahimkhel
 */
$messages['ps'] = array(
	'lqt_newmessages' => 'نوي پيغامونه',
	'lqt_contents_title' => 'مينځپانګه:',
	'lqt_reply' => 'ځوابول',
	'lqt_delete' => 'ړنګول',
	'lqt_from_talk' => 'د $1 لخوا',
	'lqt_hist_deleted' => 'ړنګ شو',
	'lqt_youhavenewmessages' => 'تاسو $1 لری.',
	'lqt_protectedfromreply_link' => 'ژغورلی',
	'lqt_subject' => 'سکالو:',
	'lqt_noreason' => 'هېڅ سبب نه دی ورکړ شوی.',
	'lqt_thread_deleted_for_sysops_deleted' => 'ړنګ شو',
	'lqt_move_noreason' => 'هېڅ سبب نه دی ورکړ شوی.',
	'lqt_delete_deleting_allreplies' => 'ټول ځوابونه',
);

/** Portuguese (Português)
 * @author 555
 * @author Lijealso
 * @author Malafaya
 */
$messages['pt'] = array(
	'lqt-desc' => 'Adiciona discussões em linha a páginas de discussão',
	'lqt-nothread' => 'Não há linhas de discussão no arquivo.',
	'lqt_newmessages' => 'Novas Mensagens',
	'lqt_movethread' => 'Mover linha de discussão para outra página',
	'lqt_deletethread' => 'Apagar ou recuperar linha de discussão',
	'lqt_browse_archive_without_recent' => 'Ver linhas de discussão arquivadas',
	'lqt_browse_archive_with_recent' => 'mais antigas',
	'lqt_recently_archived' => 'Recentemente arquivados:',
	'lqt_contents_title' => 'Conteúdo:',
	'lqt_add_header' => 'Adicionar cabeçalho',
	'lqt_new_thread' => 'Iniciar uma nova discussão',
	'lqt_in_response_to' => 'Em resposta a $1 por $2, acima:',
	'lqt_edited_notice' => 'Editado',
	'lqt_move_placeholder' => 'Esta linha de discussão é uma representação que indica que uma linha de discussão, $1, foi removida desta página para outra página de discussão.
Esta movimentação foi feita por $2 em $3.',
	'lqt_reply' => 'Responder',
	'lqt_delete' => 'Apagar',
	'lqt_undelete' => 'Recuperar',
	'lqt_permalink' => 'Ligação permanente',
	'lqt_fragment' => 'um fragmento de $1 de $2',
	'lqt_discussion_link' => 'discussão',
	'lqt_from_talk' => 'de $1',
	'lqt_newer' => '«mais recente',
	'lqt_older' => 'mais antigo»',
	'lqt_hist_comment_edited' => 'Texto do comentário editado',
	'lqt_hist_summary_changed' => 'Sumário alterado',
	'lqt_hist_reply_created' => 'Nova resposta criada',
	'lqt_hist_thread_created' => 'Novo tópico criado',
	'lqt_hist_deleted' => 'Apagado',
	'lqt_hist_undeleted' => 'Recuperado',
	'lqt_hist_moved_talkpage' => 'Movido',
	'lqt_hist_listing_subtitle' => 'A visualizar uma listagem de histórico.',
	'lqt_hist_view_whole_thread' => 'Ver histórico da linha de discussão completa',
	'lqt_hist_no_revisions_error' => 'Esta linha de discussão não tem quaisquer revisões de histórico. Esse facto é bastante estranho.',
	'lqt_hist_past_last_page_error' => 'Encontra-se para além do número de páginas de histórico existentes.',
	'lqt_hist_tooltip_newer_disabled' => 'Esta ligação está desactivada porque se encontra na primeira página.',
	'lqt_hist_tooltip_older_disabled' => 'Esta ligação está desactivada porque se encontra na última página.',
	'lqt_revision_as_of' => 'Revisão em $1.',
	'lqt_change_new_thread' => 'Esta é a revisão inicial desta linha de discussão.',
	'lqt_change_reply_created' => 'O comentário assinalado foi criado nesta revisão.',
	'lqt_change_edited_root' => 'O comentário assinalado foi editado nesta revisão.',
	'lqt_youhavenewmessages' => 'Você tem [$1 novas mensagens].',
	'lqt_changes_from' => '  de',
	'lqt_changes_summary_of' => '  de',
	'lqt_protectedfromreply' => 'Esta linha de discussão foi $1 contra respostas.',
	'lqt_protectedfromreply_link' => 'protegida',
	'lqt_subject' => 'Assunto:',
	'lqt_nosubject' => '«sem assunto»',
	'lqt_noreason' => 'Nenhum motivo foi especificado.',
	'lqt_thread_deleted_for_sysops' => 'Este tópico foi $1 e está apenas visível a administradores.',
	'lqt_thread_deleted_for_sysops_deleted' => 'apagada',
	'lqt_thread_deleted' => 'Este tópico foi eliminado',
	'lqt_summary_notice' => 'Não houve alterações a esta discussão há pelo menos $2 dias.
Se se encontra terminada, talvez queira $1.',
	'lqt_summary_notice_link' => 'escrever um sumário',
	'lqt_summary_label' => 'Esta linha de discussão foi assim sumarizada:',
	'lqt_summary_subtitle' => 'o sumário de $1.',
	'lqt_nosuchrevision' => 'Não existe essa revisão desta linha de discussão.',
	'lqt_nosuchthread' => 'Não existe esse tópico',
	'lqt_threadrequired' => 'Deverá especificar um tópico no URL.',
	'lqt_move_movingthread' => 'Movendo $1. Esta linha de discussão é parte de $2.',
	'lqt_move_torename' => "Para renomear esta linha de discussão, $1 e altere o campo 'Assunto'.",
	'lqt_move_torename_edit' => 'edite-a',
	'lqt_move_destinationtitle' => 'Título da página de discussão destino:',
	'lqt_move_move' => 'Mover',
	'lqt_move_nodestination' => 'Tem de especificar um destino.',
	'lqt_move_noreason' => 'Nenhum motivo foi especificado.',
	'lqt_move_success' => 'O tópico foi movido para $1.',
	'lqt_delete_undeleting' => 'Recuperando $1.',
	'lqt_delete_undeletethread' => 'Restaurar Tópico',
	'lqt_delete_partof' => 'Esta linha de discussão é parte de $1.',
	'lqt_delete_deleting' => 'Apagando $1 e $2 para ela.',
	'lqt_delete_deleting_allreplies' => 'todas as respostas',
	'lqt_delete_deletethread' => 'Apagar linha de discussão e respostas',
	'lqt_delete_deleted' => 'Este tópico foi eliminado.',
	'lqt_delete_undeleted' => 'Este tópico foi restaurado.',
	'lqt_delete_return' => 'Voltar para $1.',
	'lqt_delete_return_link' => 'a página de discussão',
	'lqt_delete_unallowed' => 'Não está autorizado a eliminar tópicos.',
	'lqt_talkpage_autocreate_summary' => 'Página de discussão criada automaticamente após a primeira linha de discussão ter sido colocada.',
	'lqt_header_warning_big' => 'Você está a editar um $1.',
	'lqt_header_warning_after_big' => 'Os cabeçalhos são para anúncios e prefácios.
Talvez queira em alternativa $2.',
	'lqt_header_warning_bold' => 'cabeçalho da página de discussão',
	'lqt_header_warning_new_discussion' => 'iniciar uma nova discussão',
	'lqt_sorting_order' => 'Ordenação:',
	'lqt_remember_sort' => 'Lembrar esta preferência',
	'lqt_sort_newest_changes' => 'últimos modificados primeiro',
	'lqt_sort_newest_threads' => 'novos tópicos primeiro',
	'lqt_sort_oldest_threads' => 'tópicos mais antigos primeiro',
);

/** Tarifit (Tarifit)
 * @author Jose77
 */
$messages['rif'] = array(
	'lqt_newmessages' => 'Tibratin timaynutin',
	'lqt_delete' => 'Sfaḍ',
	'lqt_protectedfromreply_link' => 'twaḥḍa',
);

/** Romanian (Română)
 * @author KlaudiuMihaila
 */
$messages['ro'] = array(
	'lqt_contents_title' => 'Cuprins:',
	'lqt_delete' => 'Şterge',
	'lqt_hist_deleted' => 'Şters',
	'lqt_thread_deleted_for_sysops_deleted' => 'şters',
);

/** Russian (Русский)
 * @author VasilievVV
 * @author Александр Сигачёв
 */
$messages['ru'] = array(
	'lqt-desc' => 'Добавляет на страницы обсуждения потоки (ветки) обсуждений',
	'lqt-nothread' => 'В архиве нет веток обсуждений.',
	'lqt_newmessages' => 'Новые сообщения',
	'lqt_movethread' => 'Переместить ветку на другую страницу',
	'lqt_deletethread' => 'Удалить/восстановить ветку',
	'lqt_browse_archive_without_recent' => 'Просмотреть архив',
	'lqt_browse_archive_with_recent' => 'старее',
	'lqt_recently_archived' => 'Недавно заархивированные:',
	'lqt_contents_title' => 'Содержит:',
	'lqt_add_header' => 'Добавить заголовок',
	'lqt_new_thread' => 'Начать новое обсуждение',
	'lqt_in_response_to' => 'В ответ на $1 от $2 выше:',
	'lqt_edited_notice' => 'Правил',
	'lqt_move_placeholder' => 'Эта ветка отображает то, что ветка $1 была перемещена на страницу $3 участником $2',
	'lqt_reply' => 'Ответить',
	'lqt_delete' => 'Удалить',
	'lqt_undelete' => 'Восстановить',
	'lqt_permalink' => 'Постоянная ссылка',
	'lqt_fragment' => 'фрагмент $1 из $2',
	'lqt_discussion_link' => 'обсуждение',
	'lqt_from_talk' => 'из $1',
	'lqt_newer' => '«новее',
	'lqt_older' => 'старее»',
	'lqt_hist_comment_edited' => 'Текст комментария отредактирован',
	'lqt_hist_summary_changed' => 'Итог изменён',
	'lqt_hist_reply_created' => 'Создан ответ',
	'lqt_hist_thread_created' => 'Новая ветка создана',
	'lqt_hist_deleted' => 'Удалена',
	'lqt_hist_undeleted' => 'Восстановлена',
	'lqt_hist_moved_talkpage' => 'Перемещена',
	'lqt_hist_listing_subtitle' => 'Просмотр истории',
	'lqt_hist_view_whole_thread' => 'Просмотреть историю для всей ветки',
	'lqt_hist_no_revisions_error' => 'Данная ветка не имеет какой либо истории. Это крайне странно.',
	'lqt_hist_past_last_page_error' => 'Вы вышли за пределы количества страниц истории которые существуют.',
	'lqt_hist_tooltip_newer_disabled' => 'Эта ссылка отключена, потому что вы находитесь на первой странице.',
	'lqt_hist_tooltip_older_disabled' => 'Эта ссылка отключена, потому что вы на последней странице.',
	'lqt_revision_as_of' => 'Версия как из $1.',
	'lqt_change_new_thread' => 'Это изначальная версия ветки.',
	'lqt_change_reply_created' => 'Выделенный комментарий был создан в этой версии.',
	'lqt_change_edited_root' => 'Выделенный комментарий был отредактирован в этой версии.',
	'lqt_youhavenewmessages' => 'Вы получили $1.',
	'lqt_changes_from' => ' c',
	'lqt_changes_summary_of' => ' из',
	'lqt_protectedfromreply' => 'Эта ветка была $1 от ответа на неё.',
	'lqt_protectedfromreply_link' => 'защищена',
	'lqt_subject' => 'Тема:',
	'lqt_nosubject' => '«без темы»',
	'lqt_noreason' => 'Не было дано причины.',
	'lqt_thread_deleted_for_sysops' => 'Эта ветка была $1 и видима только администраторам.',
	'lqt_thread_deleted_for_sysops_deleted' => 'удалена',
	'lqt_thread_deleted' => 'Эта ветка была удалена.',
	'lqt_summary_notice' => 'Это обсуждение не изменялось как минимум $2 дней. Если оно подошло к концу, вы можете $1.',
	'lqt_summary_notice_link' => 'подвести итог',
	'lqt_summary_label' => 'Для этой ветки был подведён следующий итог:',
	'lqt_summary_subtitle' => 'итог $1',
	'lqt_nosuchrevision' => 'В этой ветке нет такой версии.',
	'lqt_nosuchthread' => 'Эта ветка не существует.',
	'lqt_threadrequired' => 'Вы должны указать ветку в URL',
	'lqt_move_movingthread' => 'Перемещение $1. Эта ветка является частью $2.',
	'lqt_move_torename' => 'Для того, чтобы изменить эту ветку, $1 и измените поле "Тема".',
	'lqt_move_torename_edit' => 'отредактируйте её',
	'lqt_move_destinationtitle' => 'Название целевой страницы обсуждения:',
	'lqt_move_move' => 'Переместить',
	'lqt_move_nodestination' => 'Вы должны указать целевую страницу.',
	'lqt_move_noreason' => 'Не была указана причина.',
	'lqt_move_success' => 'Эта ветка была перемещена в $1.',
	'lqt_delete_undeleting' => 'Восстановление $1.',
	'lqt_delete_undeletethread' => 'Восстановление ветки',
	'lqt_delete_partof' => 'Эта ветка является частью $1.',
	'lqt_delete_deleting' => 'Удаление $1 и $2 на неё.',
	'lqt_delete_deleting_allreplies' => 'всех ответов',
	'lqt_delete_deletethread' => 'Удалить ветку и ответы',
	'lqt_delete_deleted' => 'Ветка была удалена.',
	'lqt_delete_undeleted' => 'Ветка была восстановлена.',
	'lqt_delete_return' => 'Возвратиться на $1.',
	'lqt_delete_return_link' => 'страницу обсуждения',
	'lqt_delete_unallowed' => 'Вы не можете удалять статьи.',
	'lqt_talkpage_autocreate_summary' => 'Страница обсуждения была автоматически созданна когда первое сообщение было отправлено.',
	'lqt_header_warning_big' => 'Вы редактируете $1.',
	'lqt_header_warning_after_big' => 'Заголовки служат для объявлений и введений. Вы можете $2.',
	'lqt_header_warning_bold' => 'заголовок страницы обсуждения',
	'lqt_header_warning_new_discussion' => 'начать новое обсуждение',
	'lqt_sorting_order' => 'Порядок сортировки:',
	'lqt_remember_sort' => 'Запомнить эти настройки',
	'lqt_sort_newest_changes' => 'последние изменённые вначале',
	'lqt_sort_newest_threads' => 'новые ветки вначале',
	'lqt_sort_oldest_threads' => 'старые ветки вначале',
);

/** Tachelhit (Tašlḥiyt)
 * @author Zanatos
 */
$messages['shi'] = array(
	'lqt_reply' => 'jawb',
	'lqt_delete' => 'msḥ',
	'lqt_hist_deleted' => 'ityumsaḥ',
	'lqt_hist_undeleted' => 'ur-ityumsaḥ',
);

/** Slovak (Slovenčina)
 * @author Helix84
 */
$messages['sk'] = array(
	'lqt-desc' => 'Pridáva organizáciu správ na diskusných stránkach do vlákien',
	'lqt-nothread' => 'V archíve sa nenachádzajú žiadne vlákna.',
	'lqt_newmessages' => 'Nové správy',
	'lqt_movethread' => 'Presunúť vlákno na inú stránku',
	'lqt_deletethread' => 'Zmazať alebo obnoviť zmazané vlákno',
	'lqt_browse_archive_without_recent' => 'Zobraziť archivované vlákna',
	'lqt_browse_archive_with_recent' => 'staršie',
	'lqt_recently_archived' => 'Nedávno archivované:',
	'lqt_contents_title' => 'Obsah:',
	'lqt_add_header' => 'Pridať hlavičku',
	'lqt_new_thread' => 'Začať novú diskusiu',
	'lqt_in_response_to' => 'Odpoveď na $1 od $2 vyššie:',
	'lqt_edited_notice' => 'Upravené',
	'lqt_move_placeholder' => 'Toto vlákno je vyhradené miesto, ktoré označuje, že vlákno $1 bolo odstránené z tejto stránky na inú diskusnú stránku. Tento presun vykonal $2 $3.',
	'lqt_reply' => 'Odpovedať',
	'lqt_delete' => 'Zmazať',
	'lqt_undelete' => 'Obnoviť zmazané',
	'lqt_permalink' => 'Trvalý odkaz',
	'lqt_fragment' => 'úryvok z $1 od $2',
	'lqt_discussion_link' => 'diskusia',
	'lqt_from_talk' => 'od $1',
	'lqt_newer' => '«novšie',
	'lqt_older' => 'staršie»',
	'lqt_hist_comment_edited' => 'Text komentára bol upravený',
	'lqt_hist_summary_changed' => 'Zhrnutie sa zmenilo',
	'lqt_hist_reply_created' => 'Bola vytvorená nová odpoveď',
	'lqt_hist_thread_created' => 'Bolo vytvorené nové vlákno',
	'lqt_hist_deleted' => 'Zmazané',
	'lqt_hist_undeleted' => 'Obnovené zmazanie',
	'lqt_hist_moved_talkpage' => 'Presunuté',
	'lqt_hist_listing_subtitle' => 'Zobrazuje sa výpis histórie.',
	'lqt_hist_view_whole_thread' => 'Zobraziť históriu celého vlákna',
	'lqt_hist_no_revisions_error' => 'Toto vlákno nemá historické revízie. To je dosť čudné.',
	'lqt_hist_past_last_page_error' => 'Prekročili ste počet stránok histórie, ktoré táto stránka má.',
	'lqt_hist_tooltip_newer_disabled' => 'Tento odkaz je nefunkčný, pretože sa nachádzate na prvej stránke.',
	'lqt_hist_tooltip_older_disabled' => 'Tento odkaz je nefunkčný, pretože sa nachádzate na poslednej stránke.',
	'lqt_revision_as_of' => 'Revízia z $1.',
	'lqt_change_new_thread' => 'Toto je prvotná revízia vlákna.',
	'lqt_change_reply_created' => 'Zvýraznený komentár bol vytvorený v tejto revízii.',
	'lqt_change_edited_root' => 'Zvýraznený komentár bol upravený v tejto revízii.',
	'lqt_youhavenewmessages' => 'Máte [$1 {{PLURAL:$1|novú správu|nové správy|nových správ}}].',
	'lqt_changes_from' => ' od',
	'lqt_changes_summary_of' => ' z',
	'lqt_protectedfromreply' => 'Toto vlákno bolo $1 proti odpovediam naň.',
	'lqt_protectedfromreply_link' => 'zamknuté',
	'lqt_subject' => 'Predmet:',
	'lqt_nosubject' => '«bez predmetu»',
	'lqt_noreason' => 'Nebol udaný dôvod.',
	'lqt_thread_deleted_for_sysops' => 'Vlákno bolo $1 a môžu ho vidieť iba správcovia.',
	'lqt_thread_deleted_for_sysops_deleted' => 'zmazané',
	'lqt_thread_deleted' => 'Toto vlákno bolo zmazané.',
	'lqt_summary_notice' => 'V tejto diskusii nenastali zmeny najmenej {{PLURAL:$2|deň|dni|dní}}. Ak diskusia skončila, mali by ste ju $1.',
	'lqt_summary_notice_link' => 'napísať zhrnutie',
	'lqt_summary_label' => 'Toto vlákno bolo zhrnuté nasledovne:',
	'lqt_summary_subtitle' => 'zhrnutie $1.',
	'lqt_nosuchrevision' => 'Takáto revízia v tomto vlákne neexistuje.',
	'lqt_nosuchthread' => 'Také vlákno neexistuje.',
	'lqt_threadrequired' => 'V URL musíte zadať vlákno.',
	'lqt_move_movingthread' => 'Presúva sa $1. Toto vlákno je súčasťou $2.',
	'lqt_move_torename' => 'Aby ste premenovali toto vlákno, $1 a zmeňte pole „Predmet“.',
	'lqt_move_torename_edit' => 'upravte ho',
	'lqt_move_destinationtitle' => 'Názov cieľovej diskusnej stránky:',
	'lqt_move_move' => 'Presunúť',
	'lqt_move_nodestination' => 'Musíte zadať cieľ.',
	'lqt_move_noreason' => 'Nebol uvedený dôvod.',
	'lqt_move_success' => 'Vlákno bolo presunuté na $1.',
	'lqt_delete_undeleting' => 'Obnovuje sa zmazané $1.',
	'lqt_delete_undeletethread' => 'Obnoviť zmazané vlákno',
	'lqt_delete_partof' => 'Toto vlákno je súčasťou $1.',
	'lqt_delete_deleting' => 'Maže sa $1 a $2 naň.',
	'lqt_delete_deleting_allreplies' => 'všetky odpovede',
	'lqt_delete_deletethread' => 'Zmazať vlákno a odpovede',
	'lqt_delete_deleted' => 'Vlákno bolo zmazané.',
	'lqt_delete_undeleted' => 'Vlákno bolo obnovené.',
	'lqt_delete_return' => 'Vrátiť sa na $1.',
	'lqt_delete_return_link' => 'diskusnú stránku',
	'lqt_delete_unallowed' => 'Nemáte povolenie mazať vlákna.',
	'lqt_talkpage_autocreate_summary' => 'Diskusná stránka bola automaticky vytvorená pri prvom príspevku do vlákna.',
	'lqt_header_warning_big' => 'Upravujete $1.',
	'lqt_header_warning_after_big' => 'Hlavičky slúžia na oznámenia a úvody. Namiesto toho môžete $2.',
	'lqt_header_warning_bold' => 'hlavička diskusnej stránky',
	'lqt_header_warning_new_discussion' => 'začať novú diskusiu',
	'lqt_sorting_order' => 'Poradie radenia:',
	'lqt_remember_sort' => 'Zapamätať si tieto preferencie',
	'lqt_sort_newest_changes' => 'posledné zmenené na začiatku',
	'lqt_sort_newest_threads' => 'najnovšie vlákna na začiatku',
	'lqt_sort_oldest_threads' => 'najstaršie vlákna na začiatku',
	'lqt-any-date' => 'Z ľubovoľného dátumu',
	'lqt-only-date' => 'Iba tieto dátumy:',
	'lqt-date-from' => 'Od',
	'lqt-date-to' => 'Do',
	'lqt-title' => 'Nadpis',
	'lqt-summary' => 'Zhrnutie',
	'lqt-older' => 'staršie',
	'lqt-newer' => 'novšie',
	'lqt-searching' => 'Hľadajú sa vlákna',
	'lqt-read-message' => 'Prečítané',
	'lqt-read-message-tooltip' => 'Odstrániť toto vlákno z nových správ.',
	'lqt-marked-read' => "Vlákno '''$1''' bolo označené ako prečítané.",
	'lqt-count-marked-read' => '$1 {{PLURAL:$1|správa bola označená ako prečítaná|správy boli označené ako prečítané|správ bolo označených ako prečítané}}.',
	'lqt-email-undo' => 'Vrátiť',
	'lqt-messages-sent' => 'Správy poslané vám:',
	'lqt-other-messages' => 'Správy na iných diskusných stránkach:',
	'lqt-new-messages' => 'Máte nové správy.',
	'lqt-email-info-undo' => 'Vrátiť vlákno, ktoré ste práve zrušili.',
	'lqt-date-info' => 'Tento odkaz je vypnutý, pretože zobrazujete vlákna bez ohľadu na dátum.',
);

/** Seeltersk (Seeltersk)
 * @author Pyt
 */
$messages['stq'] = array(
	'lqt_newmessages' => 'Näie Ättergjuchte',
	'lqt_movethread' => 'Ferschuuwe Diskussionsstrang ap ne uur Siede',
	'lqt_deletethread' => 'Läsk Diskussionsstrang of staal ju wier häär',
	'lqt_browse_archive_without_recent' => 'Archivierde Diskussionsstrange bekiekje',
	'lqt_browse_archive_with_recent' => 'allere',
	'lqt_recently_archived' => 'Knu archivierd:',
	'lqt_contents_title' => 'Inhoold:',
	'lqt_add_header' => 'Uurschrift touföigje',
	'lqt_new_thread' => 'Fang ne näie Diskussion oun',
	'lqt_in_response_to' => 'In Oantwoud ap $1 fon $2, sjuch:',
	'lqt_edited_notice' => 'Beoarbaided',
	'lqt_move_placeholder' => "''Steedehoolder, wan die Diskussionsstrang $1 ap ne uur Siede ferschäuwen wuude.''<br>
Disse Ferschuuwenge waas däin fon $2 ap n $3.",
	'lqt_reply' => 'Oantwoudje',
	'lqt_delete' => 'Läskje',
	'lqt_undelete' => 'Wierhäärstaale',
	'lqt_permalink' => 'Permalink',
	'lqt_fragment' => 'n Fragment fon n $1 fon $2',
	'lqt_discussion_link' => 'Diskussion',
	'lqt_from_talk' => 'fon $1',
	'lqt_newer' => '← jungere',
	'lqt_older' => 'allere →',
	'lqt_hist_summary_changed' => 'Touhoopefoatenge annerd',
	'lqt_hist_reply_created' => 'Näie Oantwoud moaked',
	'lqt_hist_thread_created' => 'Näien Diskussionsstrang moaked',
	'lqt_hist_deleted' => 'Läsked',
	'lqt_hist_undeleted' => 'wierhäärstoald',
	'lqt_hist_moved_talkpage' => 'ferschäuwen',
	'lqt_hist_listing_subtitle' => 'Bekiekjen fon ne Versionsgeschichte',
	'lqt_hist_view_whole_thread' => 'Versonsgeschichte fon n gansen Diskussionsstrang bekiekje',
	'lqt_hist_no_revisions_error' => 'Dissen Diskussionsstrang häd neen Versionsgeschichte. Dät is gans roar.',
	'lqt_hist_past_last_page_error' => 'Du bäst buute dät Siedenberäk fon ju Versionsgeschichte.',
	'lqt_hist_tooltip_newer_disabled' => 'Disse Ferbiendenge is deaktivierd, deeruum dät du ap ju eerste Siede bäst.',
	'lqt_hist_tooltip_older_disabled' => 'Disse Ferbiendenge is deaktivierd, deeruum dät du ap ju lääste Siede bäst.',
	'lqt_revision_as_of' => 'Versionsgeschichte fon $1.',
);

/** Sundanese (Basa Sunda)
 * @author Irwangatot
 */
$messages['su'] = array(
	'lqt_newmessages' => 'Talatah anyar',
	'lqt_contents_title' => 'Eusi:',
	'lqt_reply' => 'Balesan',
	'lqt_delete' => 'Hapus',
	'lqt_hist_deleted' => 'Hapus',
	'lqt_hist_moved_talkpage' => 'Pindah',
	'lqt_changes_from' => 'Ti',
	'lqt_changes_summary_of' => 'tina',
	'lqt_thread_deleted_for_sysops_deleted' => 'hapus',
	'lqt_move_move' => 'Pindah',
	'lqt_move_noreason' => 'Henteu di béré alesan.',
	'lqt_delete_return' => 'Balik deui ka $1.',
	'lqt_header_warning_big' => 'Anjeun keur ngédit $1',
);

/** Swedish (Svenska)
 * @author Jon Harald Søby
 * @author M.M.S.
 */
$messages['sv'] = array(
	'lqt-desc' => 'Lägger till trådade diskussioner till diskussionssidor',
	'lqt-nothread' => 'Det är inga diskussionssidor i arkivet.',
	'lqt_newmessages' => 'Nya meddelanden',
	'lqt_movethread' => 'Flytta diskussionssida till en annan sida',
	'lqt_deletethread' => 'Radera eller återupprätta diskussionssida',
	'lqt_browse_archive_without_recent' => 'Visa arkiverade diskussionssidor',
	'lqt_browse_archive_with_recent' => 'äldre',
	'lqt_recently_archived' => 'Nyligen arkiverade:',
	'lqt_contents_title' => 'Innehåll:',
	'lqt_add_header' => 'Lägg till rubrik',
	'lqt_new_thread' => 'Starta en ny diskussion',
	'lqt_in_response_to' => 'Som svar till $1 av $2, över:',
	'lqt_edited_notice' => 'Redigerad',
	'lqt_move_placeholder' => 'Den här diskussionssidan existerar endast för att visa att diskussionssidan $1 flyttades från den här sidan till en annan diskussionssida. Den här flytten gjordes av $2 $3.',
	'lqt_reply' => 'Svara',
	'lqt_delete' => 'Radera',
	'lqt_undelete' => 'Återupprätta',
	'lqt_permalink' => 'Permanent länk',
	'lqt_fragment' => 'ett fragment av $1 från $2',
	'lqt_discussion_link' => 'diskussion',
	'lqt_from_talk' => 'från $1',
	'lqt_newer' => '«nyare',
	'lqt_older' => 'äldre»',
	'lqt_hist_comment_edited' => 'Kommentartext redigerad',
	'lqt_hist_summary_changed' => 'Sammanfattning ändrad',
	'lqt_hist_reply_created' => 'Nytt svar skapat',
	'lqt_hist_thread_created' => 'Ny diskussionssida skapad',
	'lqt_hist_deleted' => 'Raderad',
	'lqt_hist_undeleted' => 'Återupprättad',
	'lqt_hist_moved_talkpage' => 'Flyttad',
	'lqt_hist_listing_subtitle' => 'Visar en historiklista.',
	'lqt_hist_view_whole_thread' => 'Se historiken för hela diskussionssidan',
	'lqt_hist_no_revisions_error' => 'Den här diskussionssidan har ingen versionshistorik. Det är ganska konstigt.',
	'lqt_hist_past_last_page_error' => 'Det finns inga fler sidor med historik.',
	'lqt_hist_tooltip_newer_disabled' => 'Den här länken är avaktiverad för du är på den första sidan.',
	'lqt_hist_tooltip_older_disabled' => 'Den här länken är avaktiverad för att du är på den sista sidan.',
	'lqt_revision_as_of' => 'Version $1.',
	'lqt_change_new_thread' => 'Detta är diskussionssidans första version.',
	'lqt_change_reply_created' => 'Den markerade kommentaren skapades i denna version.',
	'lqt_change_edited_root' => 'Den markerade kommentaren redigerades i denna version.',
	'lqt_youhavenewmessages' => 'Du har [$1 nya meddelanden].',
	'lqt_changes_from' => ' från',
	'lqt_changes_summary_of' => ' av',
	'lqt_protectedfromreply' => 'Den här diskussionssidan har blivit $1 för svar.',
	'lqt_protectedfromreply_link' => 'skyddad',
	'lqt_subject' => 'Ämne:',
	'lqt_nosubject' => '«inget motiv»',
	'lqt_noreason' => 'Ingen anledning given.',
	'lqt_thread_deleted_for_sysops' => 'Den här diskussionssidan har blivit $1 och är endast synlig för administratörer.',
	'lqt_thread_deleted_for_sysops_deleted' => 'raderad',
	'lqt_thread_deleted' => 'Den här diskussionssidan har blivit raderad.',
	'lqt_summary_notice' => 'Det har inte varit några ändringar på den här diskussionssidan på minst $2 dagar.
Om diskussionen är färdig, kommer du möjligen $1.',
	'lqt_summary_notice_link' => 'skriv en sammanfattning',
	'lqt_summary_label' => 'Denna diskussionssida har fått följande sammandrag:',
	'lqt_summary_subtitle' => 'sammanfattningen av $1.',
	'lqt_nosuchrevision' => 'Det finns ingen sådan version av den här diskussionssidan.',
	'lqt_nosuchthread' => 'Det finns ingen sådan diskussionssida.',
	'lqt_threadrequired' => 'Du måste ange en diskussionssida i URL-en.',
	'lqt_move_movingthread' => 'Flyttar $1. Den här diskussionssidan är en del av $2.',
	'lqt_move_torename' => 'För att döpa om den här diskussionssidan, $1 och ändra "Ämne"-fältet.',
	'lqt_move_torename_edit' => 'redigera den',
	'lqt_move_destinationtitle' => 'Titel på måldiskussionssida:',
	'lqt_move_move' => 'Flytta',
	'lqt_move_nodestination' => 'Du måste ange ett mål.',
	'lqt_move_noreason' => 'Ingen anledning given.',
	'lqt_move_success' => 'Den här diskussionssidan flyttades till $1.',
	'lqt_delete_undeleting' => 'Återupprättar $1.',
	'lqt_delete_undeletethread' => 'Återupprätta diskussionssida',
	'lqt_delete_partof' => 'Den här diskussionssidan är en del av $1.',
	'lqt_delete_deleting' => 'Raderar $1 och $2 dit.',
	'lqt_delete_deleting_allreplies' => 'alla svar',
	'lqt_delete_deletethread' => 'Radera diskussionssida och svar',
	'lqt_delete_deleted' => 'Diskussionssidan raderades.',
	'lqt_delete_undeleted' => 'Diskussionssidan återupprättades.',
	'lqt_delete_return' => 'Tillbaka till $1.',
	'lqt_delete_return_link' => 'diskussionssidan',
	'lqt_delete_unallowed' => 'Du är inte tillåten att radera diskussionssidor.',
	'lqt_talkpage_autocreate_summary' => 'Diskussionssida upprättades automatiskt när första diskussionssidan postades.',
	'lqt_header_warning_big' => 'Du redigerar en $1.',
	'lqt_header_warning_after_big' => 'Huvudsektioner är för annonseringar och inledningar.
Du kommer istället vilja $2.',
	'lqt_header_warning_bold' => 'diskussionssidsinledning',
	'lqt_header_warning_new_discussion' => 'starta en ny diskussion',
	'lqt_sorting_order' => 'Sorteringsföljd:',
	'lqt_remember_sort' => 'Kom ihåg denna inställning',
	'lqt_sort_newest_changes' => 'dom senaste ändrade först',
	'lqt_sort_newest_threads' => 'nyaste diskussionssidor först',
	'lqt_sort_oldest_threads' => 'äldsta diskussionssidor först',
	'lqt-any-date' => 'När som helst',
	'lqt-only-date' => 'Endast dessa datum:',
	'lqt-date-from' => 'Från',
	'lqt-date-to' => 'Till',
	'lqt-title' => 'Titel',
	'lqt-summary' => 'Sammanfattning',
	'lqt-older' => 'äldre',
	'lqt-newer' => 'nyare',
	'lqt-searching' => 'Sök efter trådar',
	'lqt-read-message' => 'Läs',
	'lqt-read-message-tooltip' => 'Ta bort den här tråden från nya meddelanden.',
	'lqt-marked-read' => "Tråden '''$1''' markerad som läst.",
	'lqt-count-marked-read' => '$1 {{PLURAL:$1|meddelande markerat som läst|meddelanden markerade som lästa}}.',
	'lqt-email-undo' => 'Ångra',
	'lqt-messages-sent' => 'Meddelanden som skickats till dig:',
);

/** Silesian (Ślůnski)
 * @author Herr Kriss
 */
$messages['szl'] = array(
	'lqt_move_move' => 'Přećep',
);

/** Telugu (తెలుగు)
 * @author Veeven
 */
$messages['te'] = array(
	'lqt_newmessages' => 'కొత్త సందేశాలు',
	'lqt_movethread' => 'చర్చాహారాన్ని మరో పేజీకి తరలించండి',
	'lqt_browse_archive_with_recent' => 'పాతవి',
	'lqt_contents_title' => 'విషయాలు:',
	'lqt_new_thread' => 'కొత్త చర్చని ప్రారంభించండి',
	'lqt_reply' => 'స్పందించు',
	'lqt_delete' => 'తొలగించు',
	'lqt_permalink' => 'స్థిరలింకు',
	'lqt_discussion_link' => 'చర్చ',
	'lqt_from_talk' => '$1 నుండి',
	'lqt_newer' => '«కొత్తవి',
	'lqt_older' => 'పాతవి»',
	'lqt_hist_comment_edited' => 'వ్యాఖ్య పాఠ్యాన్ని మార్చారు',
	'lqt_hist_summary_changed' => 'సంగ్రహం మార్చారు',
	'lqt_hist_thread_created' => 'కొత్త చర్చాహారాన్ని సృష్టించారు',
	'lqt_hist_deleted' => 'తొలగించారు',
	'lqt_hist_moved_talkpage' => 'తరలించారు',
	'lqt_hist_listing_subtitle' => 'చారిత్రక జాబితాని చూస్తున్నారు.',
	'lqt_hist_view_whole_thread' => 'మొత్తం చర్చాహారానికి చరిత్రని చూడండి',
	'lqt_hist_tooltip_newer_disabled' => 'మీరు మొదటి పేజీలో ఉన్నందున ఈ లింకుని అచేతనం చేసాం.',
	'lqt_hist_tooltip_older_disabled' => 'మీరు చివరి పేజీలో ఉన్నారు కనుక ఈ లింకుని అచేతనం చేసాం.',
	'lqt_revision_as_of' => '$1 నాటి కూర్పు.',
	'lqt_change_new_thread' => 'ఇది చర్చాహారం యొక్క మొట్టమొదటి కూర్పు.',
	'lqt_youhavenewmessages' => 'మీకు $1 ఉన్నాయి.',
	'lqt_changes_from' => 'నుండి',
	'lqt_changes_summary_of' => ' యొక్క',
	'lqt_protectedfromreply_link' => 'సంరక్షించారు',
	'lqt_subject' => 'విషయం:',
	'lqt_nosubject' => '«విషయం లేదు»',
	'lqt_noreason' => 'కారణం తెలుపలేదు.',
	'lqt_thread_deleted_for_sysops_deleted' => 'తొలగించారు',
	'lqt_thread_deleted' => 'ఈ చర్చాహారాన్ని తొలగించాం.',
	'lqt_summary_notice_link' => 'సంగ్రహం వ్రాయండి',
	'lqt_summary_label' => 'ఈ చర్చాహారం యొక్క సారాంశం ఇదీ:',
	'lqt_summary_subtitle' => '$1 యొక్క సంగ్రహం.',
	'lqt_nosuchrevision' => 'ఈ చర్చాహారానికి అటువంటి కూర్పు లేదు.',
	'lqt_nosuchthread' => 'అటువంటి చర్చాహారమేదీ లేదు.',
	'lqt_move_torename' => "ఈ చర్చాహారపు పేరు మార్చడానికి, $1 మరియు 'విషయం' అంశాన్ని మార్చండి.",
	'lqt_move_torename_edit' => 'దీన్ని మార్చండి',
	'lqt_move_destinationtitle' => 'గమ్యస్థానపు చర్చాపేజీ యొక్క శీర్షిక:',
	'lqt_move_move' => 'తరలించు',
	'lqt_move_nodestination' => 'మీరు తప్పనిసరిగా ఓ గమ్యస్థానం చెప్పాలి.',
	'lqt_move_noreason' => 'కారణం తెలుపలేదు.',
	'lqt_move_success' => 'ఈ చర్చాహారాన్ని $1కి తరలించాం.',
	'lqt_delete_partof' => 'ఈ చర్చాహారం $1లో భాగం.',
	'lqt_delete_deleting_allreplies' => 'అన్ని స్పందనలు',
	'lqt_delete_deleted' => 'చర్చాహారాన్ని తొలగించాం.',
	'lqt_delete_return' => 'తిరిగి $1కి.',
	'lqt_delete_return_link' => 'చర్చా పేజీ',
	'lqt_delete_unallowed' => 'మీరు చర్చాహారాలని తొలగించలేరు.',
	'lqt_header_warning_big' => 'మీరు ఒక $1ని మారుస్తున్నారు.',
	'lqt_header_warning_bold' => 'చర్చాపేజీ శీర్షిక',
	'lqt_header_warning_new_discussion' => 'కొత్త చర్చను మొదలుపెట్టండి',
	'lqt_sorting_order' => 'వరుస క్రమం:',
	'lqt_remember_sort' => 'ఈ అభిరుచిని గుర్తుంచుకో',
	'lqt_sort_newest_changes' => 'చివరగా మార్చినవి మొదట',
	'lqt_sort_newest_threads' => 'కొత్త చర్చాహారాలు మొదట',
	'lqt_sort_oldest_threads' => 'పాత చర్చాహారాలు మొదట',
);

/** Tetum (Tetun)
 * @author MF-Warburg
 */
$messages['tet'] = array(
	'lqt_browse_archive_with_recent' => 'tuan liu',
	'lqt_delete' => 'Halakon',
	'lqt_older' => 'tuan liu»',
	'lqt_move_move' => 'Book',
);

/** Tajik (Cyrillic) (Тоҷикӣ (Cyrillic))
 * @author Ibrahim
 */
$messages['tg-cyrl'] = array(
	'lqt-desc' => 'Илова кардани баҳси торкашӣ ба саҳифаҳои баҳс',
	'lqt-nothread' => 'Дар бойгонӣ ҳеҷ торе нест.',
	'lqt_newmessages' => 'Пайёмҳои нав',
	'lqt_movethread' => 'Кӯчонидани тор ба дигар саҳифа',
	'lqt_deletethread' => 'Ҳазф ё барқарор кардани тор',
	'lqt_browse_archive_without_recent' => 'Нигаристани торҳои бойгонишуда',
	'lqt_browse_archive_with_recent' => 'кӯҳнатар',
	'lqt_recently_archived' => 'Ба тозагӣ бойгонишуда:',
	'lqt_contents_title' => 'Мундариҷа:',
	'lqt_new_thread' => 'Оғози мубоҳисаи ҷадид',
	'lqt_in_response_to' => 'Дар посух ба $1 тавассути $2, дар боло:',
	'lqt_reply' => 'Посух додан',
	'lqt_delete' => 'Ҳафз',
	'lqt_discussion_link' => 'баҳс',
	'lqt_from_talk' => 'аз $1',
	'lqt_newer' => '«навтар',
	'lqt_older' => 'кӯҳнатар»',
	'lqt_hist_deleted' => 'Ҳазфшуда',
	'lqt_changes_from' => 'аз',
	'lqt_changes_summary_of' => 'аз',
	'lqt_protectedfromreply_link' => 'ҳифзшуда',
	'lqt_subject' => 'Мавзӯъ:',
	'lqt_nosubject' => '«мавзӯъ нест»',
	'lqt_noreason' => 'Далеле мушаххас нашудааст.',
	'lqt_thread_deleted_for_sysops_deleted' => 'ҳазфшуда',
	'lqt_summary_notice_link' => 'навиштани хулоса',
	'lqt_summary_label' => 'Ин тор ба таври зерин хулоса шудааст:',
	'lqt_summary_subtitle' => 'хулоса аз $1.',
	'lqt_nosuchrevision' => 'Чунин нусхае аз ин тор нест.',
	'lqt_nosuchthread' => 'Чунин тор нест.',
	'lqt_threadrequired' => 'Шумо бояд тореро дар нишонаи интернетии URL мушаххас кунед.',
	'lqt_move_movingthread' => 'Дар ҳоли кӯчонидани $1. Ин тор қисме аз $2 мебошад.',
	'lqt_move_torename' => "Барои тағйири номи ин тор, $1 ва ноҳияи 'Мавзӯъ'-ро тағйир диҳед.",
	'lqt_move_torename_edit' => 'инро вироиш кунед',
	'lqt_move_move' => 'Кӯчонидан',
	'lqt_move_nodestination' => 'Шумо бояд мақсадро мушаххас кунед.',
	'lqt_move_noreason' => 'Далеле мушаххас нашудааст',
	'lqt_move_success' => 'Тор ба $1 кӯчонида шуд.',
	'lqt_delete_unallowed' => 'Шумо иҷозати ҳазф кардани ин торҳоро надоред.',
	'lqt_header_warning_big' => 'Шумо дар ҳоли вироиши $1 ҳастед.',
	'lqt_header_warning_new_discussion' => 'оғози мубоҳисаи ҷадид',
	'lqt_sort_newest_threads' => 'торҳои навтарин аввал',
	'lqt_sort_oldest_threads' => 'торҳои кӯҳнатарин аввал',
);

/** Turkish (Türkçe)
 * @author Srhat
 */
$messages['tr'] = array(
	'lqt_discussion_link' => 'tartışma',
);

/** Tatar (Cyrillic) (Tatarça/Татарча (Cyrillic))
 * @author Ерней
 */
$messages['tt-cyrl'] = array(
	'lqt_subject' => 'Тема:',
	'lqt_delete_return' => '$1 битенә кайтырга.',
);

/** Vietnamese (Tiếng Việt)
 * @author Minh Nguyen
 * @author Vinhtantran
 */
$messages['vi'] = array(
	'lqt-desc' => 'Thêm những thảo luận có cấu trúc vào trang thảo luận',
	'lqt-nothread' => 'Không có luồng nào trong bản lưu.',
	'lqt_newmessages' => 'Tin nhắn mới',
	'lqt_movethread' => 'Di chuyển luồng đến trang khác',
	'lqt_deletethread' => 'Xóa hay phục hồi cuộc thảo luận',
	'lqt_browse_archive_without_recent' => 'Xem thảo luận được lưu trữ',
	'lqt_browse_archive_with_recent' => 'cũ hơn',
	'lqt_recently_archived' => 'Mới được lưu trữ:',
	'lqt_contents_title' => 'Nội dung:',
	'lqt_add_header' => 'Thêm đầu đề',
	'lqt_new_thread' => 'Bắt đầu thảo luận mới:',
	'lqt_in_response_to' => 'Trả lời $1 bởi $2 ở trên:',
	'lqt_edited_notice' => 'Đã sửa',
	'lqt_move_placeholder' => 'Luồng này là một nơi lưu trữ cho thấy rằng một luồng, $1, đã bị xóa khỏi trang này đến trang thảo luận khác.
Sự di chuyển này do $2 thực hiện vào lúc $3.',
	'lqt_reply' => 'Trả lời',
	'lqt_delete' => 'Xóa',
	'lqt_undelete' => 'Phục hồi',
	'lqt_permalink' => 'Liên kết thường trực',
	'lqt_fragment' => 'một mảnh của một $1 từ $2',
	'lqt_discussion_link' => 'thảo luận',
	'lqt_from_talk' => 'từ $1',
	'lqt_newer' => '«mới hơn',
	'lqt_older' => 'cũ hơn»',
	'lqt_hist_comment_edited' => 'Văn bản bình luận đã sửa',
	'lqt_hist_summary_changed' => 'Tóm tắt đã thay đổi',
	'lqt_hist_reply_created' => 'Hồi âm mới đã tạo ra',
	'lqt_hist_thread_created' => 'Luồng mới đã được tạo',
	'lqt_hist_deleted' => 'Đã xóa',
	'lqt_hist_undeleted' => 'Đã phục hồi',
	'lqt_hist_moved_talkpage' => 'Đã di chuyển',
	'lqt_hist_listing_subtitle' => 'Đang xem liệt kê lịch sử.',
	'lqt_hist_view_whole_thread' => 'Xem lịch sử toàn bộ luồng',
	'lqt_hist_no_revisions_error' => 'Luồng này không có bất kỳ phiên bản lịch sử nào. Điều đó khá là lạ.',
	'lqt_hist_past_last_page_error' => 'Bạn đã vượt quá số trang lịch sử tồn tại.',
	'lqt_hist_tooltip_newer_disabled' => 'Liên kết này bị tắt vì bạn đang ở trang nhất.',
	'lqt_hist_tooltip_older_disabled' => 'Liên kết bị tắt vì bạn đang ở trang cuối.',
	'lqt_revision_as_of' => 'Phiên bản vào lúc $1.',
	'lqt_change_new_thread' => 'Đây là phiên bản khởi đầu của luồng.',
	'lqt_change_reply_created' => 'Câu bình luận được đánh dấu đã được tạo ra trong phiên bản này.',
	'lqt_change_edited_root' => 'Câu bình luận được đánh dấu đã được sửa đổi trong phiên bản này.',
	'lqt_youhavenewmessages' => 'Bạn có $1.',
	'lqt_changes_from' => '  từ',
	'lqt_changes_summary_of' => '  của',
	'lqt_protectedfromreply' => 'Luồng này đã được $1 từ những gì trả lời.',
	'lqt_protectedfromreply_link' => 'khóa',
	'lqt_subject' => 'Chủ đề:',
	'lqt_nosubject' => '«không có chủ đề»',
	'lqt_noreason' => 'Không đưa ra lý do.',
	'lqt_thread_deleted_for_sysops' => 'Luồng này đã được $1 và chỉ có sysop mới thấy được.',
	'lqt_thread_deleted_for_sysops_deleted' => 'xóa',
	'lqt_thread_deleted' => 'Luồng này đã bị xóa.',
	'lqt_summary_notice' => 'Không có thay đổi nào trong thảo luận này trong ít nhất $2 ngày.
Nếu nó đã đi đến kết thúc, có thể bạn sẽ muốn $1.',
	'lqt_summary_notice_link' => 'viết một câu tóm tắt',
	'lqt_summary_label' => 'Luồng này đã được tóm tắt như sau:',
	'lqt_summary_subtitle' => 'tóm tắt của $1.',
	'lqt_nosuchrevision' => 'Không có phiên bản như vậy trong luồng này.',
	'lqt_nosuchthread' => 'Không có luồng như vậy.',
	'lqt_threadrequired' => 'Bạn hãy chỉ định một luồng trong URL.',
	'lqt_move_movingthread' => 'Đang di chuyển $1. Luồng này là một phần của $2.',
	'lqt_move_torename' => 'Để đổi tên cuộc thảo luận này, $1 và đổi dòng “Chủ đề”.',
	'lqt_move_torename_edit' => 'sửa đổi nó',
	'lqt_move_destinationtitle' => 'Tựa đề của trang thảo luận đích:',
	'lqt_move_move' => 'Di chuyển',
	'lqt_move_nodestination' => 'Bạn phải chỉ định đích.',
	'lqt_move_noreason' => 'Không đưa ra lý do.',
	'lqt_move_success' => 'Luồng đã được di chuyển đến $1.',
	'lqt_delete_undeleting' => 'Đang phục hồi $1.',
	'lqt_delete_undeletethread' => 'Phục hồi cuộc thảo luận',
	'lqt_delete_partof' => 'Luồng này là một phần của $1.',
	'lqt_delete_deleting' => 'Đang xóa $1 và $2 vào nó.',
	'lqt_delete_deleting_allreplies' => 'các trả lời',
	'lqt_delete_deletethread' => 'Xóa cuộc thảo luận và các trả lời',
	'lqt_delete_deleted' => 'Luồng đã bị xóa.',
	'lqt_delete_undeleted' => 'Luồng đã được phục hồi.',
	'lqt_delete_return' => 'Trở về $1.',
	'lqt_delete_return_link' => 'trang thảo luận',
	'lqt_delete_unallowed' => 'Bạn không được phép xóa luồng.',
	'lqt_talkpage_autocreate_summary' => 'Trang thảo luận được tự động tạo ra khi luồng đầu tiên được đăng lên.',
	'lqt_header_warning_big' => 'Bạn đang sửa đổi một $1.',
	'lqt_header_warning_after_big' => 'Đầu đề dùng để thông báo và mở đầu.
Thay vào đó bạn có thể muốn $2.',
	'lqt_header_warning_bold' => 'tiêu đề trang thảo luận',
	'lqt_header_warning_new_discussion' => 'bắ đầu cuộc thảo luận mới',
	'lqt_sorting_order' => 'Thứ tự xếp:',
	'lqt_remember_sort' => 'Nhớ lựa chọn này',
	'lqt_sort_newest_changes' => 'xem lần cuối sửa đổi trước',
	'lqt_sort_newest_threads' => 'xem các luồng mới nhất trước',
	'lqt_sort_oldest_threads' => 'xem các luồng cũ nhất trước',
);

/** Volapük (Volapük)
 * @author Malafaya
 */
$messages['vo'] = array(
	'lqt_newmessages' => 'Nuns Nulik',
	'lqt_contents_title' => 'Ninäd:',
	'lqt_youhavenewmessages' => 'Labol $1.',
);

/** Yue (粵語)
 * @author Shinjiman
 */
$messages['yue'] = array(
	'lqt_newmessages' => '新訊息',
	'lqt_movethread' => '搬討論串去另一版',
	'lqt_deletethread' => '刪除或反刪除討論串',
	'lqt_browse_archive_without_recent' => '去睇歸檔嘅討論串',
	'lqt_browse_archive_with_recent' => '更舊嘅',
	'lqt_recently_archived' => '先前做咗嘅歸檔:',
	'lqt_contents_title' => '內容:',
	'lqt_add_header' => '加版頭',
	'lqt_new_thread' => '開一個新討論',
	'lqt_in_response_to' => '回覆由$2所寫嘅$1，以上:',
	'lqt_edited_notice' => '編輯咗',
	'lqt_move_placeholder' => '呢個討論串係一個放置指定討論串$1響呢版度刪除咗，搬咗去另一版討論版度。呢次嘅搬動係由$2響$3做嘅。',
	'lqt_reply' => '回覆',
	'lqt_delete' => '刪除',
	'lqt_undelete' => '反刪除',
	'lqt_permalink' => '永久連結',
	'lqt_fragment' => '自$2中，$1嘅一塊碎片',
	'lqt_discussion_link' => '討論',
	'lqt_from_talk' => '自$1',
	'lqt_newer' => '«更新',
	'lqt_older' => '更舊»',
	'lqt_hist_comment_edited' => '編輯咗摘要文字',
	'lqt_hist_summary_changed' => '改咗摘要',
	'lqt_hist_reply_created' => '開咗新回覆',
	'lqt_hist_thread_created' => '開咗新討論串',
	'lqt_hist_deleted' => '刪除咗',
	'lqt_hist_undeleted' => '反刪除咗',
	'lqt_hist_moved_talkpage' => '搬咗',
	'lqt_hist_listing_subtitle' => '睇緊個歷史一覽。',
	'lqt_hist_view_whole_thread' => '睇成個討論串嘅歷史　',
	'lqt_hist_no_revisions_error' => '呢個討論無任何嘅歷史修訂。好似好唔尋常。',
	'lqt_hist_past_last_page_error' => '你響已經存在嘅歷史版數存在範圍之外。',
	'lqt_hist_tooltip_newer_disabled' => '呢個連結停用咗，因為你而家響第一版。',
	'lqt_hist_tooltip_older_disabled' => '呢個連結停用咗，因為你而家響最後一版。',
	'lqt_revision_as_of' => '響$1嘅修訂。',
	'lqt_change_new_thread' => '呢個係個討論串嘅最初修訂。',
	'lqt_change_reply_created' => '嘜咗嘅評論響呢次修訂度開過。',
	'lqt_change_edited_root' => '嘜咗嘅評論響呢次修訂度改過。',
	'lqt_youhavenewmessages' => '你有$1。',
	'lqt_changes_from' => '由',
	'lqt_changes_summary_of' => '之',
	'lqt_protectedfromreply' => '呢個要回覆嘅討論串己經$1。',
	'lqt_protectedfromreply_link' => '保護咗',
	'lqt_subject' => '主旨:',
	'lqt_nosubject' => '«無主旨»',
	'lqt_noreason' => '無畀到原因。',
	'lqt_thread_deleted_for_sysops' => '呢個討論串已經$1，只係可以俾操作員睇到。',
	'lqt_thread_deleted_for_sysops_deleted' => '刪除咗',
	'lqt_thread_deleted' => '呢個討論串已經刪除咗。',
	'lqt_summary_notice' => '如果呢個討論有結論嘅話，鼓勵你去$1。呢個討論已經最少有$2日無更改過。　',
	'lqt_summary_notice_link' => '寫一個摘要',
	'lqt_summary_label' => '呢個討論串已經摘要做:',
	'lqt_summary_subtitle' => '$1嘅摘要。',
	'lqt_nosuchrevision' => '響呢個討論串度無所要嘅修訂。',
	'lqt_nosuchthread' => '呢度無呢個討論串。',
	'lqt_threadrequired' => '你一定要響個URL度指定一個討論串。',
	'lqt_move_movingthread' => '搬緊$1。呢個討論串係$2嘅一部份。',
	'lqt_move_torename' => "要改呢個討論串嘅名，$1再改'主旨'一欄。",
	'lqt_move_torename_edit' => '編輯佢',
	'lqt_move_destinationtitle' => '目標討論頁嘅標題:',
	'lqt_move_move' => '搬',
	'lqt_move_nodestination' => '你一定要指定一個目標。',
	'lqt_move_noreason' => '無畀到原因。',
	'lqt_move_success' => '個討論串已經搬咗去$1。',
	'lqt_delete_undeleting' => '反刪除緊$1。',
	'lqt_delete_undeletethread' => '反刪除討論串',
	'lqt_delete_partof' => '呢個討論串係$1嘅一部份。',
	'lqt_delete_deleting' => '刪除緊$1同$2。',
	'lqt_delete_deleting_allreplies' => '全部回覆',
	'lqt_delete_deletethread' => '刪除討論串同回覆',
	'lqt_delete_deleted' => '個討論串已經刪除咗。',
	'lqt_delete_undeleted' => '個討論串已經反刪除咗。',
	'lqt_delete_return' => '返去$1。',
	'lqt_delete_return_link' => '討論頁',
	'lqt_delete_unallowed' => '你唔容許去刪除討論串。',
	'lqt_talkpage_autocreate_summary' => '當第一個討論串貼咗之後自動開討論版。',
	'lqt_header_warning_big' => '你而家編輯緊$1。',
	'lqt_header_warning_after_big' => '用來做公告同埋導言嘅版頭。你可能想去$2。',
	'lqt_header_warning_bold' => '討論頁版頭',
	'lqt_header_warning_new_discussion' => '開始一個新討論',
);

/** Simplified Chinese (‪中文(简体)‬)
 * @author Shinjiman
 */
$messages['zh-hans'] = array(
	'lqt_newmessages' => '新信息',
	'lqt_movethread' => '移动讨论串到另一页面',
	'lqt_deletethread' => '删除或反删除讨论串',
	'lqt_browse_archive_without_recent' => '查看存档的讨论串',
	'lqt_browse_archive_with_recent' => '更旧的',
	'lqt_recently_archived' => '先前的存档:',
	'lqt_contents_title' => '内容:',
	'lqt_add_header' => '加入页顶',
	'lqt_new_thread' => '开始一个新讨论',
	'lqt_in_response_to' => '回覆由$2所编写的$1，以上:',
	'lqt_edited_notice' => '已编辑',
	'lqt_move_placeholder' => '这个讨论串是一个放置指定讨论串$1在这个页面中删除了，移动到另一个讨论页面中。这次的移动是由$2于$3所作的。',
	'lqt_reply' => '回覆',
	'lqt_delete' => '删除',
	'lqt_undelete' => '反删除',
	'lqt_permalink' => '永久链接',
	'lqt_fragment' => '自$2中，$1的一块碎片',
	'lqt_discussion_link' => '讨论',
	'lqt_from_talk' => '自$1',
	'lqt_newer' => '?更新',
	'lqt_older' => '更旧?',
	'lqt_hist_comment_edited' => '已编辑摘要文字',
	'lqt_hist_summary_changed' => '已更改摘要',
	'lqt_hist_reply_created' => '已建立新的回覆',
	'lqt_hist_thread_created' => '已建立新的讨论串',
	'lqt_hist_deleted' => '已经删除',
	'lqt_hist_undeleted' => '已经反删除',
	'lqt_hist_moved_talkpage' => '已移动',
	'lqt_hist_listing_subtitle' => '正在查看历史列表。',
	'lqt_hist_view_whole_thread' => '查看整个讨论串的历史',
	'lqt_hist_no_revisions_error' => '这个讨论没有任何的历史修订。好像很不寻常。',
	'lqt_hist_past_last_page_error' => '您在已经存在的历史版数存在范围以外。',
	'lqt_hist_tooltip_newer_disabled' => '这个链接已经停用，因为您现正于第一页。',
	'lqt_hist_tooltip_older_disabled' => '这个链接已经停用，因为您现正于最后一页。',
	'lqt_revision_as_of' => '于$1的修订。',
	'lqt_change_new_thread' => '这个是讨论串中的最初修订。',
	'lqt_change_reply_created' => '这醒目提示的评论在这次修订中建立。',
	'lqt_change_edited_root' => '这醒目提示的评论在这次修订中作过编辑。',
	'lqt_youhavenewmessages' => '您有$1。',
	'lqt_changes_from' => '由',
	'lqt_changes_summary_of' => '之',
	'lqt_protectedfromreply' => '这个要回覆的讨论串$1。',
	'lqt_protectedfromreply_link' => '已保护',
	'lqt_subject' => '主旨:',
	'lqt_nosubject' => '?无主旨?',
	'lqt_noreason' => '无给出原因。',
	'lqt_thread_deleted_for_sysops' => '这个讨论串$1，只可以给操作员可见。',
	'lqt_thread_deleted_for_sysops_deleted' => '已删除',
	'lqt_thread_deleted' => '这个讨论串已经删除。',
	'lqt_summary_notice' => '如果这个讨论是有结论的话，鼓励您去$1。这个讨论已经最少有$2天没有更改。',
	'lqt_summary_notice_link' => '写一个摘要',
	'lqt_summary_label' => '这个讨论串已经摘要为:',
	'lqt_summary_subtitle' => '$1的摘要。',
	'lqt_nosuchrevision' => '在这个讨论串中没有所要的修订。',
	'lqt_nosuchthread' => '这里没有这个讨论串。',
	'lqt_threadrequired' => '您必须要在URL中指定一个讨论串。',
	'lqt_move_movingthread' => '正在移动$1。这个讨论串是$2的一部份。',
	'lqt_move_torename' => "要重新命名这个讨论串，$1再更改'主旨'一栏。",
	'lqt_move_torename_edit' => '编辑它',
	'lqt_move_destinationtitle' => '目标讨论页的标题:',
	'lqt_move_move' => '移动',
	'lqt_move_nodestination' => '您必须要指定一个目标。',
	'lqt_move_noreason' => '无给出原因。',
	'lqt_move_success' => '讨论串已经移动到$1。',
	'lqt_delete_undeleting' => '正在反删除$1。',
	'lqt_delete_undeletethread' => '反删除讨论串',
	'lqt_delete_partof' => '这个讨论串是$1的一部份。',
	'lqt_delete_deleting' => '正在删除$1和$2。',
	'lqt_delete_deleting_allreplies' => '所有回覆',
	'lqt_delete_deletethread' => '删除讨论串和回覆',
	'lqt_delete_deleted' => '讨论串已经删除。',
	'lqt_delete_undeleted' => '讨论串已经反删除。',
	'lqt_delete_return' => '回到$1。',
	'lqt_delete_return_link' => '讨论页',
	'lqt_delete_unallowed' => '您是不容许去删除讨论串。',
	'lqt_talkpage_autocreate_summary' => '当第一个讨论串贴上后自动建立讨论页。',
	'lqt_header_warning_big' => '您现正在编辑$1。',
	'lqt_header_warning_after_big' => '用来作为公告和导言的页顶。您可能想去$2。',
	'lqt_header_warning_bold' => '讨论页页顶',
	'lqt_header_warning_new_discussion' => '开始一个新的讨论',
);

/** Traditional Chinese (‪中文(繁體)‬)
 * @author Alexsh
 * @author Shinjiman
 */
$messages['zh-hant'] = array(
	'lqt_newmessages' => '新信息',
	'lqt_movethread' => '移動討論串到另一頁面',
	'lqt_deletethread' => '刪除或反刪除討論串',
	'lqt_browse_archive_without_recent' => '檢視存檔的討論串',
	'lqt_browse_archive_with_recent' => '更舊的',
	'lqt_recently_archived' => '先前的存檔:',
	'lqt_contents_title' => '內容:',
	'lqt_add_header' => '加入頁頂',
	'lqt_new_thread' => '開始一個新討論',
	'lqt_in_response_to' => '回覆由$2所編寫的$1，以上:',
	'lqt_edited_notice' => '已編輯',
	'lqt_move_placeholder' => '這個討論串是一個放置指定討論串$1在這個頁面中刪除了，移動到另一個討論版中。這次的移動是由$2於$3所作的。',
	'lqt_reply' => '回覆',
	'lqt_delete' => '刪除',
	'lqt_undelete' => '反刪除',
	'lqt_permalink' => '永久連結',
	'lqt_fragment' => '自$2中，$1的一塊碎片',
	'lqt_discussion_link' => '討論',
	'lqt_from_talk' => '自$1',
	'lqt_newer' => '«更新',
	'lqt_older' => '更舊»',
	'lqt_hist_comment_edited' => '已編輯摘要文字',
	'lqt_hist_summary_changed' => '已更改摘要',
	'lqt_hist_reply_created' => '已建立新的回覆',
	'lqt_hist_thread_created' => '已建立新的討論串',
	'lqt_hist_deleted' => '已經刪除',
	'lqt_hist_undeleted' => '已經反刪除',
	'lqt_hist_moved_talkpage' => '已移動',
	'lqt_hist_listing_subtitle' => '正在檢視歷史列表。',
	'lqt_hist_view_whole_thread' => '檢視整個討論串的歷史',
	'lqt_hist_no_revisions_error' => '這個討論沒有任何的歷史修訂。好像很不尋常。',
	'lqt_hist_past_last_page_error' => '您在已經存在的歷史版數存在範圍以外。',
	'lqt_hist_tooltip_newer_disabled' => '這個連結已經停用，因為您現正於第一頁。',
	'lqt_hist_tooltip_older_disabled' => '這個連結已經停用，因為您現正於最後一頁。',
	'lqt_revision_as_of' => '於$1的修訂。',
	'lqt_change_new_thread' => '這個是討論串中的最初修訂。',
	'lqt_change_reply_created' => '這醒目提示的評論在這次修訂中建立。',
	'lqt_change_edited_root' => '這醒目提示的評論在這次修訂中作過編輯。',
	'lqt_youhavenewmessages' => '您有$1。',
	'lqt_changes_from' => '由',
	'lqt_changes_summary_of' => '之',
	'lqt_protectedfromreply' => '這個要回覆的討論串$1。',
	'lqt_protectedfromreply_link' => '已保護',
	'lqt_subject' => '主旨:',
	'lqt_nosubject' => '«無主旨»',
	'lqt_noreason' => '無給出原因。',
	'lqt_thread_deleted_for_sysops' => '這個討論串$1，只可以給操作員可見。',
	'lqt_thread_deleted_for_sysops_deleted' => '已刪除',
	'lqt_thread_deleted' => '這個討論串已經刪除。',
	'lqt_summary_notice' => '如果這個討論是有結論的話，鼓勵您去$1。這個討論已經最少有$2天沒有更改。',
	'lqt_summary_notice_link' => '寫一個摘要',
	'lqt_summary_label' => '這個討論串已經摘要為:',
	'lqt_summary_subtitle' => '$1的摘要。',
	'lqt_nosuchrevision' => '在這個討論串中沒有所要的修訂。',
	'lqt_nosuchthread' => '這裡沒有這個討論串。',
	'lqt_threadrequired' => '您必須要在URL中指定一個討論串。',
	'lqt_move_movingthread' => '正在移動$1。這個討論串是$2的一部份。',
	'lqt_move_torename' => "要重新命名這個討論串，$1再更改'主旨'一欄。",
	'lqt_move_torename_edit' => '編輯它',
	'lqt_move_destinationtitle' => '目標討論頁的標題:',
	'lqt_move_move' => '重新命名',
	'lqt_move_nodestination' => '您必須要指定一個目標。',
	'lqt_move_noreason' => '無給出原因。',
	'lqt_move_success' => '討論串已經移動到$1。',
	'lqt_delete_undeleting' => '正在反刪除$1。',
	'lqt_delete_undeletethread' => '反刪除討論串',
	'lqt_delete_partof' => '這個討論串是$1的一部份。',
	'lqt_delete_deleting' => '正在刪除$1和$2。',
	'lqt_delete_deleting_allreplies' => '所有回覆',
	'lqt_delete_deletethread' => '刪除討論串和回覆',
	'lqt_delete_deleted' => '討論串已經刪除。',
	'lqt_delete_undeleted' => '討論串已經反刪除。',
	'lqt_delete_return' => '回到$1。',
	'lqt_delete_return_link' => '討論頁',
	'lqt_delete_unallowed' => '您是不容許去刪除討論串。',
	'lqt_talkpage_autocreate_summary' => '當第一個討論串貼上後自動建立討論頁。',
	'lqt_header_warning_big' => '您現正在編輯$1。',
	'lqt_header_warning_after_big' => '用來作為公告和導言的頁頂。您可能想去$2。',
	'lqt_header_warning_bold' => '討論頁頁頂',
	'lqt_header_warning_new_discussion' => '開始一個新的討論',
);

