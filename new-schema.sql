-- New LiquidThreads schema
-- Original: Andrew Garrett, 2011-01-28
-- Updates:


-- Channel table
-- See LiquidThreadsChannel class
CREATE TABLE /*_*/lqt_channel (
	lqc_id bigint(10) unsigned primary key not null auto_increment,
	
	-- NS/title pair of the talk page this channel is attached to.
	lqc_page_namespace int(2) not null,
	lqc_page_title varbinary(255) not null
) /*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/lqc_page_namespace_title ON /*_*/lqt_channel (lqc_page_namespace, lqc_page_title);


-- Topic table
-- See LiquidThreadsTopic class
CREATE TABLE /*_*/lqt_topic (
	lqt_id bigint(10) unsigned PRIMARY KEY not null auto_increment,
	
	-- The current version of this topic.
	-- Foreign key to lqt_topic_version.ltv_id
	lqt_current_version bigint(10) unsigned not null,
	
	-- Cache of the number of replies
	lqt_replies int unsigned not null,
	
	-- The Channel that this topic is contained in.
	-- Foreign key to lqt_channel.lqc_id
	lqt_channel bigint(10) unsigned not null,
	
	lqt_touched varbinary(14) not null
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/lqt_topic_channel ON /*_*/lqt_topic (lqt_channel,lqt_touched);


-- Topic Version table
-- See LiquidThreadsTopicVersion class
CREATE TABLE /*_*/lqt_topic_version (
	ltv_id bigint(10) unsigned PRIMARY KEY not null auto_increment,
	
	-- The topic to which this version applies
	-- Foreign key to lqt_topic.lqt_id
	ltv_topic bigint(10) unsigned not null,
	
	-- VERSION METADATA
	
	-- User IP/ID. One is set, the other is NULL
	ltv_user_id bigint(10) unsigned null,
	ltv_user_ip varbinary(64) null,
	-- Timestamp of the change
	ltv_timestamp varbinary(14) not null,
	-- Edit comment for this change, if applicable
	ltv_comment TINYBLOB,
	
	-- Bitfield for single-version deletion
	ltv_deleted tinyint unsigned NOT NULL default 0,
	
	-- Pointer to the text table, stores the summary text.
	-- Foreign key to text.old_id
	ltv_summary_text_id bigint(10) unsigned not null,
	
	ltv_subject TINYBLOB NOT NULL,
	ltv_channel bigint(10) unsigned not null
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/ltv_topic_timestamp ON /*_*/lqt_topic_version (ltv_topic, ltv_timestamp);
CREATE INDEX /*i*/ltv_user_id_ip ON /*_*/lqt_topic_version (ltv_user_id, ltv_user_ip);


-- Post table
-- See LiquidThreadsPost class
CREATE TABLE /*_*/lqt_post (
	lqp_id bigint(10) unsigned PRIMARY KEY not null auto_increment,

	-- Current version of this post.
	-- Foreign key to lqt_topic_version.lpv_id
	lqp_current_version bigint(10) unsigned not null,
	
	-- Cache of the number of replies
	lqp_replies int unsigned not null,
	
	-- Everything below this is a cache of the content of the current version.
	-- It's here to simplify queries.
	
	-- Topic that this post currently belongs to.
	-- Foreign key to lqt_topic.lqt_id
	lqp_topic bigint(10) unsigned not null,
	
	-- Parent post. Potentially blank, if it's at the top level in the topic.
	-- Foreign key to lqt_post.lqp_id
	lqp_parent_post bigint(10) unsigned null
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/lqp_topic_parent ON /*_*/lqt_post (lqp_topic, lqp_parent_post);


-- Post Version table
-- See LiquidThreadsPostVersion class
CREATE TABLE /*_*/lqt_post_version (
	lpv_id bigint(10) unsigned PRIMARY KEY not null auto_increment,
	
	-- The post to which this version applies.
	-- Foreign key to lqt_post.lqp_id
	lpv_post bigint(10) unsigned not null,
	
	-- VERSION METADATA
	
	-- User IP/ID. One is set, the other is NULL
	lpv_user_id bigint(10) unsigned null,
	lpv_user_ip varbinary(64) null,
	-- Timestamp of the change
	lpv_timestamp varbinary(14) not null,
	-- Edit comment for this change, if applicable
	lpv_comment TINYBLOB,
	
	-- Bitfield for single-version deletion
	lpv_deleted tinyint unsigned NOT NULL default 0,
	
	-- ACTUAL DATA
	
	-- User IP/ID for the ORIGINAL POSTER
	-- That is, the person to which this post is attributed.
	-- As with above, one is set, the other is NULL
	lpv_poster_id bigint(10) unsigned not null,
	lpv_poster_ip varbinary(64) null,
	
	-- Pointer to the text table, stores the comment text.
	-- Foreign key to text.old_id
	lpv_text_id bigint(10) unsigned not null,
	
	-- Ancestry, location.
	-- The topic that this post is a part of.
	-- Foreign key to lqt_topic.lqt_id
	lpv_topic bigint(10) unsigned not null,
	
	-- Parent post. Potentially blank, if it's at the top level in the topic.
	-- Foreign key to lqt_post.lqp_id
	lpv_parent_post bigint(10) unsigned null,
	
	-- Signature
	lpv_signature TINYBLOB NOT NULL,
	
	-- Attributed date/time
	lpv_post_time varbinary(14) not null
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/lpv_post_timestamp ON /*_*/lqt_post_version (lpv_post, lpv_timestamp);
