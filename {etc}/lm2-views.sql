=============== Don't think this one's in use =================
CREATE OR REPLACE
ALGORITHM = MERGE
SQL SECURITY INVOKER
VIEW gizmo71_views.lm2_reg_choices
AS
SELECT l.id_poll * 100 + l.id_choice AS id, l.id_poll, l.id_member, realName, l.id_choice, label
FROM smf_log_polls l
JOIN smf_poll_choices c USING (id_poll, id_choice)
JOIN smf_members m USING (id_member)
WHERE l.id_poll IN (
	SELECT id_poll
	FROM gizmo71_lm2.lm2_championships
	JOIN gizmo71_lm2.lm2_event_groups ON event_group = id_event_group
	JOIN smf_topics ON reg_topic = id_topic
	WHERE fulltime_poll_choice IS NOT NULL
	AND id_poll IS NOT NULL
)

CREATE OR REPLACE
ALGORITHM = MERGE
SQL SECURITY INVOKER
VIEW gizmo71_views.lm2_classifications
AS
SELECT id_event_entry, (
	SELECT id_car_classification
	FROM gizmo71_lm2.lm2_car_classification
	JOIN gizmo71_lm2.lm2_sim_cars USING (car)
	JOIN gizmo71_lm2.lm2_event_group_tree ON event_group = container
	WHERE id_sim_car = sim_car AND gizmo71_lm2.lm2_events.event_group = contained
	ORDER BY depth
	LIMIT 1
) AS id_car_classification
FROM gizmo71_lm2.lm2_event_entries
JOIN gizmo71_lm2.lm2_events ON id_event = event;

CREATE OR REPLACE
ALGORITHM = MERGE
SQL SECURITY INVOKER
VIEW smf_lm2i_members
AS
SELECT gizmo71_smf.smf_members.ID_MEMBER AS ID_MEMBER
, gizmo71_smf.smf_members.memberName AS memberName
, gizmo71_smf.smf_members.dateRegistered AS dateRegistered
, gizmo71_smf.smf_members.posts AS posts
, gizmo71_smf.smf_members.ID_GROUP AS ID_GROUP
, gizmo71_smf.smf_members.lngfile AS lngfile
, gizmo71_smf.smf_members.lastLogin AS lastLogin
, gizmo71_smf.smf_members.realName AS realName
, gizmo71_smf.smf_members.instantMessages AS instantMessages
, gizmo71_smf.smf_members.unreadMessages AS unreadMessages
, gizmo71_smf.smf_members.pm_ignore_list AS pm_ignore_list
, gizmo71_smf.smf_members.passwd AS passwd
, gizmo71_smf.smf_members.emailAddress AS emailAddress
, gizmo71_smf.smf_members.personalText AS personalText
, gizmo71_smf.smf_members.gender AS gender
, gizmo71_smf.smf_members.birthdate AS birthdate
, gizmo71_smf.smf_members.websiteTitle AS websiteTitle
, gizmo71_smf.smf_members.websiteUrl AS websiteUrl
, gizmo71_smf.smf_members.location AS location
, gizmo71_smf.smf_members.ICQ AS ICQ
, gizmo71_smf.smf_members.AIM AS AIM
, gizmo71_smf.smf_members.YIM AS YIM
, gizmo71_smf.smf_members.MSN AS MSN
, gizmo71_smf.smf_members.hideEmail AS hideEmail
, gizmo71_smf.smf_members.showOnline AS showOnline
, gizmo71_smf.smf_members.timeFormat AS timeFormat
, gizmo71_smf.smf_members.signature AS signature
, gizmo71_smf.smf_members.timeOffset AS timeOffset
, gizmo71_smf.smf_members.avatar AS avatar
, gizmo71_smf.smf_members.pm_email_notify AS pm_email_notify
, gizmo71_smf.smf_members.karmaBad AS karmaBad
, gizmo71_smf.smf_members.karmaGood AS karmaGood
, gizmo71_smf.smf_members.usertitle AS usertitle
, gizmo71_smf.smf_members.notifyAnnouncements AS notifyAnnouncements
, gizmo71_smf.smf_members.notifyOnce AS notifyOnce
, gizmo71_smf.smf_members.memberIP AS memberIP
, gizmo71_smf.smf_members.secretQuestion AS secretQuestion
, gizmo71_smf.smf_members.secretAnswer AS secretAnswer
, gizmo71_smf.smf_members.ID_THEME AS ID_THEME
, gizmo71_smf.smf_members.is_activated AS is_activated
, gizmo71_smf.smf_members.is_spammer AS is_spammer
, gizmo71_smf.smf_members.validation_code AS validation_code
, gizmo71_smf.smf_members.ID_MSG_LAST_VISIT AS ID_MSG_LAST_VISIT
, gizmo71_smf.smf_members.additionalGroups AS additionalGroups
, gizmo71_smf.smf_members.smileySet AS smileySet
, gizmo71_smf.smf_members.ID_POST_GROUP AS ID_POST_GROUP
, gizmo71_smf.smf_members.totalTimeLoggedIn AS totalTimeLoggedIn
, gizmo71_smf.smf_members.passwordSalt AS passwordSalt
, gizmo71_smf.smf_members.messageLabels AS messageLabels
, gizmo71_smf.smf_members.buddy_list AS buddy_list
, gizmo71_smf.smf_members.notifySendBody AS notifySendBody
, gizmo71_smf.smf_members.notifyTypes AS notifyTypes
, gizmo71_smf.smf_members.memberIP2 AS memberIP2
, COUNT(distinct gizmo71_lm2.lm2_event_entries.event) AS races
FROM gizmo71_smf.smf_members
LEFT JOIN gizmo71_lm2.lm2_event_entries on gizmo71_lm2.lm2_event_entries.member = gizmo71_smf.smf_members.ID_MEMBER
GROUP BY gizmo71_smf.smf_members.ID_MEMBER;

+++ ALSO make lm2_registered_drivers a view +++
