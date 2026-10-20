DELETE FROM list_options WHERE list_id = "risklevel";
DELETE FROM list_options WHERE list_id = "lists" AND option_id = "risklevel";
/*M!999999\- enable the sandbox mode */ 
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

INSERT INTO `list_options` (`list_id`, `option_id`, `title`, `seq`, `is_default`, `option_value`, `mapping`, `notes`, `codes`, `toggle_setting_1`, `toggle_setting_2`, `activity`, `subtype`, `edit_options`, `timestamp`, `last_updated`) VALUES ('lists','risklevel','Risk Level',15,0,0,'',NULL,'',0,0,1,'',1,'2026-10-06 22:27:38','2026-10-06 22:27:38');
INSERT INTO `list_options` (`list_id`, `option_id`, `title`, `seq`, `is_default`, `option_value`, `mapping`, `notes`, `codes`, `toggle_setting_1`, `toggle_setting_2`, `activity`, `subtype`, `edit_options`, `timestamp`, `last_updated`) VALUES ('risklevel','low','Low',1,0,0,'',NULL,'',0,0,1,'',1,'2026-10-06 22:27:38','2026-10-06 22:27:38');
INSERT INTO `list_options` (`list_id`, `option_id`, `title`, `seq`, `is_default`, `option_value`, `mapping`, `notes`, `codes`, `toggle_setting_1`, `toggle_setting_2`, `activity`, `subtype`, `edit_options`, `timestamp`, `last_updated`) VALUES ('risklevel','medium','Medium',2,1,0,'',NULL,'',0,0,1,'',1,'2026-10-06 22:27:38','2026-10-06 22:27:38');
INSERT INTO `list_options` (`list_id`, `option_id`, `title`, `seq`, `is_default`, `option_value`, `mapping`, `notes`, `codes`, `toggle_setting_1`, `toggle_setting_2`, `activity`, `subtype`, `edit_options`, `timestamp`, `last_updated`) VALUES ('risklevel','high','High',3,0,0,'',NULL,'',0,0,1,'',1,'2026-10-06 22:27:38','2026-10-06 22:27:38');
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

DELETE FROM layout_options WHERE form_id = "LBTref";
DELETE FROM layout_group_properties WHERE grp_form_id = "LBTref";
/*M!999999\- enable the sandbox mode */ 
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

INSERT INTO `layout_group_properties` (`grp_form_id`, `grp_group_id`, `grp_title`, `grp_subtitle`, `grp_mapping`, `grp_seq`, `grp_activity`, `grp_repeats`, `grp_columns`, `grp_size`, `grp_issue_type`, `grp_aco_spec`, `grp_save_close`, `grp_init_open`, `grp_referrals`, `grp_unchecked`, `grp_services`, `grp_products`, `grp_diags`, `grp_last_update`) VALUES ('LBTref','','Referral','','Transactions',0,1,0,0,0,'','',0,0,0,0,'','','',NULL);
INSERT INTO `layout_group_properties` (`grp_form_id`, `grp_group_id`, `grp_title`, `grp_subtitle`, `grp_mapping`, `grp_seq`, `grp_activity`, `grp_repeats`, `grp_columns`, `grp_size`, `grp_issue_type`, `grp_aco_spec`, `grp_save_close`, `grp_init_open`, `grp_referrals`, `grp_unchecked`, `grp_services`, `grp_products`, `grp_diags`, `grp_last_update`) VALUES ('LBTref','1','Referral','','',0,1,0,0,0,'','',0,0,0,0,'','','',NULL);
INSERT INTO `layout_group_properties` (`grp_form_id`, `grp_group_id`, `grp_title`, `grp_subtitle`, `grp_mapping`, `grp_seq`, `grp_activity`, `grp_repeats`, `grp_columns`, `grp_size`, `grp_issue_type`, `grp_aco_spec`, `grp_save_close`, `grp_init_open`, `grp_referrals`, `grp_unchecked`, `grp_services`, `grp_products`, `grp_diags`, `grp_last_update`) VALUES ('LBTref','2','Counter-Referral','','',0,1,0,0,0,'','',0,0,0,0,'','','',NULL);
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

/*M!999999\- enable the sandbox mode */ 
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

INSERT INTO `layout_options` (`form_id`, `field_id`, `group_id`, `title`, `seq`, `data_type`, `uor`, `fld_length`, `max_length`, `list_id`, `titlecols`, `datacols`, `default_value`, `edit_options`, `description`, `fld_rows`, `list_backup_id`, `source`, `conditions`, `validation`, `codes`) VALUES ('LBTref','refer_date','1','Referral Date',1,4,2,0,0,'',1,1,'C','D','Date of referral',0,'','F',NULL,NULL,'');
INSERT INTO `layout_options` (`form_id`, `field_id`, `group_id`, `title`, `seq`, `data_type`, `uor`, `fld_length`, `max_length`, `list_id`, `titlecols`, `datacols`, `default_value`, `edit_options`, `description`, `fld_rows`, `list_backup_id`, `source`, `conditions`, `validation`, `codes`) VALUES ('LBTref','refer_from','1','Refer By',2,10,2,0,0,'',1,1,'','','Referral By',0,'','F',NULL,NULL,'');
INSERT INTO `layout_options` (`form_id`, `field_id`, `group_id`, `title`, `seq`, `data_type`, `uor`, `fld_length`, `max_length`, `list_id`, `titlecols`, `datacols`, `default_value`, `edit_options`, `description`, `fld_rows`, `list_backup_id`, `source`, `conditions`, `validation`, `codes`) VALUES ('LBTref','refer_external','1','External Referral',3,1,1,0,0,'boolean',1,1,'','','External referral?',0,'','F',NULL,NULL,'');
INSERT INTO `layout_options` (`form_id`, `field_id`, `group_id`, `title`, `seq`, `data_type`, `uor`, `fld_length`, `max_length`, `list_id`, `titlecols`, `datacols`, `default_value`, `edit_options`, `description`, `fld_rows`, `list_backup_id`, `source`, `conditions`, `validation`, `codes`) VALUES ('LBTref','refer_to','1','Refer To',4,14,2,0,0,'',1,1,'','','Referral To',0,'','F',NULL,NULL,'');
INSERT INTO `layout_options` (`form_id`, `field_id`, `group_id`, `title`, `seq`, `data_type`, `uor`, `fld_length`, `max_length`, `list_id`, `titlecols`, `datacols`, `default_value`, `edit_options`, `description`, `fld_rows`, `list_backup_id`, `source`, `conditions`, `validation`, `codes`) VALUES ('LBTref','body','1','Reason',5,3,2,30,0,'',1,1,'','','Reason for referral',3,'','F',NULL,NULL,'');
INSERT INTO `layout_options` (`form_id`, `field_id`, `group_id`, `title`, `seq`, `data_type`, `uor`, `fld_length`, `max_length`, `list_id`, `titlecols`, `datacols`, `default_value`, `edit_options`, `description`, `fld_rows`, `list_backup_id`, `source`, `conditions`, `validation`, `codes`) VALUES ('LBTref','refer_diag','1','Referrer Diagnosis',6,2,1,30,255,'',1,1,'','X','Referrer diagnosis',0,'','F',NULL,NULL,'');
INSERT INTO `layout_options` (`form_id`, `field_id`, `group_id`, `title`, `seq`, `data_type`, `uor`, `fld_length`, `max_length`, `list_id`, `titlecols`, `datacols`, `default_value`, `edit_options`, `description`, `fld_rows`, `list_backup_id`, `source`, `conditions`, `validation`, `codes`) VALUES ('LBTref','refer_risk_level','1','Risk Level',7,1,1,0,0,'risklevel',1,1,'','','Level of urgency',0,'','F',NULL,NULL,'');
INSERT INTO `layout_options` (`form_id`, `field_id`, `group_id`, `title`, `seq`, `data_type`, `uor`, `fld_length`, `max_length`, `list_id`, `titlecols`, `datacols`, `default_value`, `edit_options`, `description`, `fld_rows`, `list_backup_id`, `source`, `conditions`, `validation`, `codes`) VALUES ('LBTref','refer_vitals','1','Include Vitals',8,1,1,0,0,'boolean',1,1,'','','Include vitals data?',0,'','F',NULL,NULL,'');
INSERT INTO `layout_options` (`form_id`, `field_id`, `group_id`, `title`, `seq`, `data_type`, `uor`, `fld_length`, `max_length`, `list_id`, `titlecols`, `datacols`, `default_value`, `edit_options`, `description`, `fld_rows`, `list_backup_id`, `source`, `conditions`, `validation`, `codes`) VALUES ('LBTref','refer_related_code','1','Requested Service',9,15,1,30,255,'',1,1,'','','Billing Code for Requested Service',0,'','F',NULL,NULL,'');
INSERT INTO `layout_options` (`form_id`, `field_id`, `group_id`, `title`, `seq`, `data_type`, `uor`, `fld_length`, `max_length`, `list_id`, `titlecols`, `datacols`, `default_value`, `edit_options`, `description`, `fld_rows`, `list_backup_id`, `source`, `conditions`, `validation`, `codes`) VALUES ('LBTref','billing_facility_id','1','Patient Billing Facility',11,35,1,0,0,'',1,1,'','','Billing facility that patient claims are billed against',0,'','F',NULL,NULL,'');
INSERT INTO `layout_options` (`form_id`, `field_id`, `group_id`, `title`, `seq`, `data_type`, `uor`, `fld_length`, `max_length`, `list_id`, `titlecols`, `datacols`, `default_value`, `edit_options`, `description`, `fld_rows`, `list_backup_id`, `source`, `conditions`, `validation`, `codes`) VALUES ('LBTref','reply_date','2','Reply Date',10,4,1,0,0,'',1,1,'','D','Date of reply',0,'','F',NULL,NULL,'');
INSERT INTO `layout_options` (`form_id`, `field_id`, `group_id`, `title`, `seq`, `data_type`, `uor`, `fld_length`, `max_length`, `list_id`, `titlecols`, `datacols`, `default_value`, `edit_options`, `description`, `fld_rows`, `list_backup_id`, `source`, `conditions`, `validation`, `codes`) VALUES ('LBTref','reply_from','2','Reply From',11,2,1,30,255,'',1,1,'','','Who replied?',0,'','F',NULL,NULL,'');
INSERT INTO `layout_options` (`form_id`, `field_id`, `group_id`, `title`, `seq`, `data_type`, `uor`, `fld_length`, `max_length`, `list_id`, `titlecols`, `datacols`, `default_value`, `edit_options`, `description`, `fld_rows`, `list_backup_id`, `source`, `conditions`, `validation`, `codes`) VALUES ('LBTref','reply_init_diag','2','Presumed Diagnosis',12,2,1,30,255,'',1,1,'','','Presumed diagnosis by specialist',0,'','F',NULL,NULL,'');
INSERT INTO `layout_options` (`form_id`, `field_id`, `group_id`, `title`, `seq`, `data_type`, `uor`, `fld_length`, `max_length`, `list_id`, `titlecols`, `datacols`, `default_value`, `edit_options`, `description`, `fld_rows`, `list_backup_id`, `source`, `conditions`, `validation`, `codes`) VALUES ('LBTref','reply_final_diag','2','Final Diagnosis',13,2,1,30,255,'',1,1,'','','Final diagnosis by specialist',0,'','F',NULL,NULL,'');
INSERT INTO `layout_options` (`form_id`, `field_id`, `group_id`, `title`, `seq`, `data_type`, `uor`, `fld_length`, `max_length`, `list_id`, `titlecols`, `datacols`, `default_value`, `edit_options`, `description`, `fld_rows`, `list_backup_id`, `source`, `conditions`, `validation`, `codes`) VALUES ('LBTref','reply_documents','2','Documents',14,2,1,30,255,'',1,1,'','','Where may related scanned or paper documents be found?',0,'','F',NULL,NULL,'');
INSERT INTO `layout_options` (`form_id`, `field_id`, `group_id`, `title`, `seq`, `data_type`, `uor`, `fld_length`, `max_length`, `list_id`, `titlecols`, `datacols`, `default_value`, `edit_options`, `description`, `fld_rows`, `list_backup_id`, `source`, `conditions`, `validation`, `codes`) VALUES ('LBTref','reply_findings','2','Findings',15,3,1,30,0,'',1,1,'','','Findings by specialist',3,'','F',NULL,NULL,'');
INSERT INTO `layout_options` (`form_id`, `field_id`, `group_id`, `title`, `seq`, `data_type`, `uor`, `fld_length`, `max_length`, `list_id`, `titlecols`, `datacols`, `default_value`, `edit_options`, `description`, `fld_rows`, `list_backup_id`, `source`, `conditions`, `validation`, `codes`) VALUES ('LBTref','reply_services','2','Services Provided',16,3,1,30,0,'',1,1,'','','Service provided by specialist',3,'','F',NULL,NULL,'');
INSERT INTO `layout_options` (`form_id`, `field_id`, `group_id`, `title`, `seq`, `data_type`, `uor`, `fld_length`, `max_length`, `list_id`, `titlecols`, `datacols`, `default_value`, `edit_options`, `description`, `fld_rows`, `list_backup_id`, `source`, `conditions`, `validation`, `codes`) VALUES ('LBTref','reply_recommend','2','Recommendations',17,3,1,30,0,'',1,1,'','','Recommendations by specialist',3,'','F',NULL,NULL,'');
INSERT INTO `layout_options` (`form_id`, `field_id`, `group_id`, `title`, `seq`, `data_type`, `uor`, `fld_length`, `max_length`, `list_id`, `titlecols`, `datacols`, `default_value`, `edit_options`, `description`, `fld_rows`, `list_backup_id`, `source`, `conditions`, `validation`, `codes`) VALUES ('LBTref','reply_rx_refer','2','Prescriptions/Referrals',18,3,1,30,0,'',1,1,'','','Prescriptions and/or referrals by specialist',3,'','F',NULL,NULL,'');
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

