-- Postgres version of the schema for the LiquidThreads extension

BEGIN;

CREATE SEQUENCE thread_thread_id;
CREATE TABLE thread (
  thread_id                INTEGER         NULL PRIMARY KEY DEFAULT nextval('thread_thread_id'),
  thread_root              INTEGER     NOT NULL,
  thread_ancestor          INTEGER     NOT NULL,
  thread_parent            INTEGER         NULL,
  thread_summary_page      INTEGER         NULL,
  thread_subject           TEXT            NULL,
  thread_author_id         INTEGER         NULL,
  thread_author_name       TEXT            NULL,
  thread_modified          TIMESTAMPTZ NOT NULL,
  thread_created           TIMESTAMPTZ NOT NULL,
  thread_editedness        SMALLINT    NOT NULL DEFAULT 0,
  thread_article_namespace SMALLINT    NOT NULL,
  thread_article_title     TEXT        NOT NULL,
  thread_article_id        INTEGER     NOT NULL,
  thread_type              SMALLINT    NOT NULL DEFAULT 0,
  thread_sortkey           TEXT        NOT NULL DEFAULT '',
  thread_replies           INTEGER     NOT NULL DEFAULT -1,
  thread_signature         varchar(255)    NULL
);

CREATE UNIQUE INDEX thread_root ON thread(thread_root);
CREATE INDEX thread_ancestor ON thread(thread_ancestor, thread_parent);
CREATE INDEX thread_article_title ON thread(thread_article_namespace, thread_article_title, thread_sortkey);
CREATE INDEX thread_article ON thread(thread_article_id, thread_sortkey);
CREATE INDEX thread_modified ON thread(thread_modified);
CREATE INDEX thread_created ON thread(thread_created);
CREATE INDEX thread_summary_page ON thread(thread_summary_page);
CREATE INDEX thread_author ON thread(thread_author_id,thread_author_name);
CREATE INDEX thread_sortkey ON thread(thread_sortkey);

CREATE TABLE historical_thread (
  hthread_id            INTEGER NOT NULL,
  hthread_revision      INTEGER NOT NULL,
  hthread_contents      TEXT    NOT NULL,
  hthread_change_type   INTEGER NOT NULL,
  hthread_change_object INTEGER     NULL
);
CREATE UNIQUE INDEX historical_thread_unique ON historical_thread(hthread_id, hthread_revision);

CREATE TABLE user_message_state (
  ums_user           INTEGER NOT NULL,
  ums_thread         INTEGER NOT NULL,
  ums_conversation   INTEGER NOT NULL DEFAULT 0,
  ums_read_timestamp TIMESTAMPTZ
);
CREATE UNIQUE INDEX user_message_state_unique ON user_message_state(ums_user, ums_thread);
CREATE INDEX /*i*/ums_user_conversation ON /*_*/user_message_state (ums_user,ums_conversation);

CREATE SEQUENCE thread_history_th_id;
CREATE TABLE thread_history (
  th_id             INTEGER NOT NULL PRIMARY KEY DEFAULT nextval('thread_history_th_id'),
  th_thread         INTEGER NOT NULL,
  th_timestamp      TIMESTAMPTZ NOT NULL,
  th_user           INTEGER NOT NULL,
  th_user_text      TEXT    NOT NULL,
  th_change_type    INTEGER NOT NULL,
  th_change_object  INTEGER NOT NULL,
  th_change_comment TEXT    NOT NULL,
  th_content        TEXT    NOT NULL
);
CREATE INDEX thread_history_thread ON thread_history(th_thread,th_timestamp);
CREATE INDEX thread_history_user ON thread_history(th_user,th_user_text);

-- Storage for "pending" relationships from import
CREATE TABLE /*_*/thread_pending_relationship (
	tpr_thread int NOT NULL,
	tpr_relationship varchar(64) NOT NULL,
	tpr_title varchar(255) NOT NULL,
	tpr_type varchar(32) NOT NULL,
	PRIMARY KEY (tpr_thread,tpr_relationship)
) /*$wgDBTableOptions*/;

-- Storage for reactions
CREATE TABLE /*_*/thread_reaction (
	tr_thread int NOT NULL,
	tr_user int NOT NULL,
	tr_user_text varchar(255) NOT NULL,
	tr_type varchar(64) NOT NULL,
	tr_value int NOT NULL,
	
	PRIMARY KEY (tr_thread,tr_user,tr_user_text,tr_type,tr_value)
) /*$wgDBTableOptions*/;
CREATE INDEX thread_reaction_user_text_value ON thread_reaction (tr_user,tr_user_text,tr_type,tr_value);

COMMIT;