-- Demo structure only: no original members, transactions or accounts.
SET NAMES utf8mb4;
CREATE TABLE `tbl_member` (
  `mem_id` int(11) NOT NULL,
  `mem_group` varchar(255) NOT NULL,
  `mem_number` varchar(255) NOT NULL,
  `mem_fullname` varchar(255) NOT NULL,
  `mem_class` varchar(255) NOT NULL,
  `mem_saveby` varchar(255) NOT NULL,
  `mem_savedate` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `tbl_price` (
  `pr_id` int(11) NOT NULL,
  `pr_year` int(11) NOT NULL,
  `pr_date` date NOT NULL,
  `pr_number` varchar(255) NOT NULL,
  `pr_price` decimal(18,2) NOT NULL,
  `pr_saveby` varchar(255) NOT NULL,
  `pr_savedate` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `tbl_rubber` (
  `ru_id` int(11) NOT NULL,
  `ru_date` date NOT NULL,
  `ru_lan` varchar(255) NOT NULL,
  `ru_group` varchar(255) NOT NULL,
  `ru_number` varchar(255) NOT NULL,
  `ru_fullname` varchar(255) NOT NULL,
  `ru_class` varchar(255) NOT NULL,
  `ru_quantity` decimal(18,2) NOT NULL,
  `ru_value` decimal(18,2) NOT NULL,
  `ru_hoon` decimal(18,2) NOT NULL,
  `ru_loan` decimal(18,2) NOT NULL,
  `ru_shortdebt` decimal(18,2) NOT NULL,
  `ru_deposit` decimal(18,2) NOT NULL,
  `ru_tradeloan` decimal(18,2) NOT NULL,
  `ru_insurance` decimal(18,2) NOT NULL,
  `ru_expend` decimal(18,2) NOT NULL,
  `ru_netvalue` decimal(18,2) NOT NULL,
  `ru_saveby` varchar(255) NOT NULL,
  `ru_savedate` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `tbl_user` (
  `user_id` int(11) NOT NULL,
  `user_username` varchar(255) NOT NULL,
  `user_password` varchar(255) NOT NULL,
  `user_fullname` varchar(255) NOT NULL,
  `user_level` varchar(255) NOT NULL,
  `user_status` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `tbl_member`
  ADD PRIMARY KEY (`mem_id`);

ALTER TABLE `tbl_price`
  ADD PRIMARY KEY (`pr_id`);

ALTER TABLE `tbl_rubber`
  ADD PRIMARY KEY (`ru_id`);

ALTER TABLE `tbl_user`
  ADD PRIMARY KEY (`user_id`);

ALTER TABLE `tbl_member`
  MODIFY `mem_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `tbl_price`
  MODIFY `pr_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `tbl_rubber`
  MODIFY `ru_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `tbl_user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE tbl_member ADD COLUMN mem_personcode VARCHAR(4) NOT NULL DEFAULT '', ADD COLUMN mem_birthtext VARCHAR(8) NOT NULL DEFAULT '';
