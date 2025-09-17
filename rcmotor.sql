-- MariaDB dump 10.19  Distrib 10.4.28-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: rcmotor
-- ------------------------------------------------------
-- Server version	10.4.28-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `akun`
--

DROP TABLE IF EXISTS `akun`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `akun` (
  `id_akun` int(11) NOT NULL AUTO_INCREMENT,
  `kode_akun` varchar(20) NOT NULL,
  `nama_akun` varchar(100) NOT NULL,
  `jenis_akun` enum('Kas','Bank','Hutang','Piutang','Modal','Pendapatan','Beban') NOT NULL,
  `keterangan` text DEFAULT NULL,
  `id_bengkel` int(11) NOT NULL,
  `tanggal_dibuat` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_akun`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `akun`
--

LOCK TABLES `akun` WRITE;
/*!40000 ALTER TABLE `akun` DISABLE KEYS */;
INSERT INTO `akun` VALUES (1,'1001','BRI','Kas','Kas',4,'2025-09-17 11:12:07'),(2,'1002','Mandiri','Bank','Bank',4,'2025-09-17 11:12:40'),(3,'1003','BNI','Hutang','Hutang',4,'2025-09-17 11:17:27'),(4,'1004','BCA','Piutang','',4,'2025-09-17 11:18:14'),(5,'1005','SeaBank','Modal','tes',4,'2025-09-17 11:22:56');
/*!40000 ALTER TABLE `akun` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bengkels`
--

DROP TABLE IF EXISTS `bengkels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bengkels` (
  `id_bengkel` int(11) NOT NULL AUTO_INCREMENT,
  `nama_bengkel` varchar(100) NOT NULL,
  `alamat` text DEFAULT NULL,
  `telepon` varchar(20) DEFAULT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `status` enum('aktif','non_aktif') NOT NULL DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_bengkel`),
  KEY `fk_bengkels_owner` (`owner_id`),
  CONSTRAINT `fk_bengkels_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id_user`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bengkels`
--

LOCK TABLES `bengkels` WRITE;
/*!40000 ALTER TABLE `bengkels` DISABLE KEYS */;
INSERT INTO `bengkels` VALUES (2,'RC MOTOR 2','INDIHIANG','11651655165',NULL,'aktif','2025-08-17 14:23:14','2025-08-17 14:32:33'),(3,'RC MOTOR 1','dskldkls','298980',NULL,'aktif','2025-08-17 14:33:23','2025-08-17 14:33:47'),(4,'RC MOTOR','tes','832478924792',6,'aktif','2025-08-18 11:39:37','2025-08-18 11:39:37');
/*!40000 ALTER TABLE `bengkels` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `harga_jual_sparepart`
--

DROP TABLE IF EXISTS `harga_jual_sparepart`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `harga_jual_sparepart` (
  `id_harga_jual` int(11) NOT NULL AUTO_INCREMENT,
  `sparepart_id` int(11) NOT NULL,
  `tipe_harga` int(11) NOT NULL COMMENT '1, 2, 3, or 4',
  `persentase_jual` decimal(5,2) DEFAULT NULL,
  `harga_jual` decimal(15,2) DEFAULT NULL,
  `satuan_jual_id` int(11) DEFAULT NULL,
  `isi_per_pcs_jual` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_harga_jual`),
  KEY `sparepart_id` (`sparepart_id`),
  KEY `satuan_jual_id` (`satuan_jual_id`),
  CONSTRAINT `harga_jual_sparepart_ibfk_1` FOREIGN KEY (`sparepart_id`) REFERENCES `spareparts` (`id_sparepart`),
  CONSTRAINT `harga_jual_sparepart_ibfk_2` FOREIGN KEY (`satuan_jual_id`) REFERENCES `satuan` (`id_satuan`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `harga_jual_sparepart`
--

LOCK TABLES `harga_jual_sparepart` WRITE;
/*!40000 ALTER TABLE `harga_jual_sparepart` DISABLE KEYS */;
INSERT INTO `harga_jual_sparepart` VALUES (1,1,1,0.00,130.00,1,1),(2,1,2,0.00,1300.00,2,0),(3,2,1,0.00,100.00,1,1),(4,3,1,0.00,2500.00,1,1),(5,3,2,0.00,25000.00,2,10),(7,4,1,0.00,20000.00,1,1);
/*!40000 ALTER TABLE `harga_jual_sparepart` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jenis_servis`
--

DROP TABLE IF EXISTS `jenis_servis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jenis_servis` (
  `id_servis` int(11) NOT NULL AUTO_INCREMENT,
  `nama_servis` varchar(100) NOT NULL,
  `biaya` decimal(10,2) NOT NULL,
  `bengkel_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_servis`),
  KEY `bengkel_id` (`bengkel_id`),
  CONSTRAINT `jenis_servis_ibfk_1` FOREIGN KEY (`bengkel_id`) REFERENCES `bengkels` (`id_bengkel`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jenis_servis`
--

LOCK TABLES `jenis_servis` WRITE;
/*!40000 ALTER TABLE `jenis_servis` DISABLE KEYS */;
INSERT INTO `jenis_servis` VALUES (1,'Ganti Oli',15000.00,4,'2025-08-30 04:10:20','2025-08-30 04:10:20');
/*!40000 ALTER TABLE `jenis_servis` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kategori_sparepart`
--

DROP TABLE IF EXISTS `kategori_sparepart`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kategori_sparepart` (
  `id_kategori` int(11) NOT NULL AUTO_INCREMENT,
  `nama_kategori` varchar(100) NOT NULL,
  PRIMARY KEY (`id_kategori`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kategori_sparepart`
--

LOCK TABLES `kategori_sparepart` WRITE;
/*!40000 ALTER TABLE `kategori_sparepart` DISABLE KEYS */;
INSERT INTO `kategori_sparepart` VALUES (1,'Tes Kategori'),(2,'teset');
/*!40000 ALTER TABLE `kategori_sparepart` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `merk_sparepart`
--

DROP TABLE IF EXISTS `merk_sparepart`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `merk_sparepart` (
  `id_merk` int(11) NOT NULL AUTO_INCREMENT,
  `nama_merk` varchar(100) NOT NULL,
  PRIMARY KEY (`id_merk`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `merk_sparepart`
--

LOCK TABLES `merk_sparepart` WRITE;
/*!40000 ALTER TABLE `merk_sparepart` DISABLE KEYS */;
INSERT INTO `merk_sparepart` VALUES (1,'test');
/*!40000 ALTER TABLE `merk_sparepart` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `merks`
--

DROP TABLE IF EXISTS `merks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `merks` (
  `id_merk` int(11) NOT NULL AUTO_INCREMENT,
  `nama_merk` varchar(50) NOT NULL,
  `bengkel_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_merk`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `merks`
--

LOCK TABLES `merks` WRITE;
/*!40000 ALTER TABLE `merks` DISABLE KEYS */;
INSERT INTO `merks` VALUES (1,'Test Merk',4,'2025-08-30 04:19:57','2025-08-30 04:19:57');
/*!40000 ALTER TABLE `merks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pelanggans`
--

DROP TABLE IF EXISTS `pelanggans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pelanggans` (
  `id_pelanggan` int(11) NOT NULL AUTO_INCREMENT,
  `nama_pelanggan` varchar(100) NOT NULL,
  `alamat` text DEFAULT NULL,
  `telepon` varchar(20) DEFAULT NULL,
  `bengkel_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_pelanggan`),
  KEY `bengkel_id` (`bengkel_id`),
  CONSTRAINT `pelanggans_ibfk_1` FOREIGN KEY (`bengkel_id`) REFERENCES `bengkels` (`id_bengkel`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pelanggans`
--

LOCK TABLES `pelanggans` WRITE;
/*!40000 ALTER TABLE `pelanggans` DISABLE KEYS */;
INSERT INTO `pelanggans` VALUES (1,'mdfndsk','kndkjfnk','knsdfks',4,'2025-08-30 03:55:41','2025-08-30 03:55:41');
/*!40000 ALTER TABLE `pelanggans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `satuan`
--

DROP TABLE IF EXISTS `satuan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `satuan` (
  `id_satuan` int(11) NOT NULL AUTO_INCREMENT,
  `nama_satuan` varchar(50) NOT NULL,
  PRIMARY KEY (`id_satuan`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `satuan`
--

LOCK TABLES `satuan` WRITE;
/*!40000 ALTER TABLE `satuan` DISABLE KEYS */;
INSERT INTO `satuan` VALUES (1,'PCS'),(2,'KARTON');
/*!40000 ALTER TABLE `satuan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `spare_parts`
--

DROP TABLE IF EXISTS `spare_parts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `spare_parts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_part` varchar(100) NOT NULL,
  `barcode` varchar(50) DEFAULT NULL,
  `stok` int(11) DEFAULT 0,
  `harga_beli` decimal(10,2) NOT NULL,
  `harga_jual` decimal(10,2) NOT NULL,
  `merk_id` int(11) DEFAULT NULL,
  `submerk_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `bengkel_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  CONSTRAINT `spare_parts_ibfk_1` FOREIGN KEY (`merk_id`) REFERENCES `merks` (`id_merk`) ON DELETE SET NULL,
  CONSTRAINT `spare_parts_ibfk_2` FOREIGN KEY (`submerk_id`) REFERENCES `submerks` (`id_submerk`) ON DELETE SET NULL,
  CONSTRAINT `spare_parts_ibfk_3` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id_supplier`) ON DELETE SET NULL,
  CONSTRAINT `spare_parts_ibfk_4` FOREIGN KEY (`bengkel_id`) REFERENCES `bengkels` (`id_bengkel`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `spare_parts`
--

LOCK TABLES `spare_parts` WRITE;
/*!40000 ALTER TABLE `spare_parts` DISABLE KEYS */;
/*!40000 ALTER TABLE `spare_parts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `spareparts`
--

DROP TABLE IF EXISTS `spareparts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `spareparts` (
  `id_sparepart` int(11) NOT NULL AUTO_INCREMENT,
  `kode_sparepart` varchar(50) NOT NULL,
  `nama_sparepart` varchar(255) NOT NULL,
  `kategori_id` int(11) NOT NULL,
  `merk_id` int(11) NOT NULL,
  `lokasi_rak` varchar(50) DEFAULT NULL,
  `harga_beli` decimal(15,2) NOT NULL,
  `satuan_beli_id` int(11) NOT NULL,
  `isi_per_pcs_beli` int(11) NOT NULL,
  `hpp_per_pcs` decimal(15,2) NOT NULL,
  `stok_pcs` int(11) NOT NULL,
  `stok_minimal` int(11) NOT NULL,
  `bengkel_id` int(11) NOT NULL,
  PRIMARY KEY (`id_sparepart`),
  UNIQUE KEY `kode_sparepart` (`kode_sparepart`),
  KEY `kategori_id` (`kategori_id`),
  KEY `merk_id` (`merk_id`),
  KEY `satuan_beli_id` (`satuan_beli_id`),
  KEY `bengkel_id` (`bengkel_id`),
  CONSTRAINT `spareparts_ibfk_1` FOREIGN KEY (`kategori_id`) REFERENCES `kategori_sparepart` (`id_kategori`),
  CONSTRAINT `spareparts_ibfk_2` FOREIGN KEY (`merk_id`) REFERENCES `merk_sparepart` (`id_merk`),
  CONSTRAINT `spareparts_ibfk_3` FOREIGN KEY (`satuan_beli_id`) REFERENCES `satuan` (`id_satuan`),
  CONSTRAINT `spareparts_ibfk_4` FOREIGN KEY (`bengkel_id`) REFERENCES `bengkels` (`id_bengkel`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `spareparts`
--

LOCK TABLES `spareparts` WRITE;
/*!40000 ALTER TABLE `spareparts` DISABLE KEYS */;
INSERT INTO `spareparts` VALUES (1,'SP-1755528666','TEST',1,1,'',1000.00,2,10,100.00,59,10,4),(2,'4-SPART-816110258','kjdbfkjdnk',1,1,'',1000.00,2,100,10.00,59,10,4),(3,'4-SPART-357233986','jbvjsdbjsj',1,1,'',20000.00,2,10,2000.00,17,1,4),(4,'4-SPART-741863857','dsjdncdjsk',1,1,'',10000.00,1,1,10000.00,10,5,4);
/*!40000 ALTER TABLE `spareparts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stok_masuk`
--

DROP TABLE IF EXISTS `stok_masuk`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stok_masuk` (
  `id_stok_masuk` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal_masuk` date NOT NULL,
  `spare_part_id` int(11) NOT NULL,
  `jumlah_masuk` int(11) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `bengkel_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_stok_masuk`),
  KEY `spare_part_id` (`spare_part_id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `bengkel_id` (`bengkel_id`),
  CONSTRAINT `stok_masuk_ibfk_1` FOREIGN KEY (`spare_part_id`) REFERENCES `spare_parts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stok_masuk_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id_supplier`) ON DELETE SET NULL,
  CONSTRAINT `stok_masuk_ibfk_3` FOREIGN KEY (`bengkel_id`) REFERENCES `bengkels` (`id_bengkel`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stok_masuk`
--

LOCK TABLES `stok_masuk` WRITE;
/*!40000 ALTER TABLE `stok_masuk` DISABLE KEYS */;
/*!40000 ALTER TABLE `stok_masuk` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stok_opnames`
--

DROP TABLE IF EXISTS `stok_opnames`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stok_opnames` (
  `id_stok_opname` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal_opname` date NOT NULL,
  `spare_part_id` int(11) NOT NULL,
  `stok_sistem` int(11) NOT NULL,
  `stok_fisik` int(11) NOT NULL,
  `selisih` int(11) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `bengkel_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_stok_opname`),
  KEY `bengkel_id` (`bengkel_id`),
  KEY `spare_part_id` (`spare_part_id`),
  CONSTRAINT `stok_opnames_ibfk_2` FOREIGN KEY (`bengkel_id`) REFERENCES `bengkels` (`id_bengkel`) ON DELETE CASCADE,
  CONSTRAINT `stok_opnames_ibfk_3` FOREIGN KEY (`spare_part_id`) REFERENCES `spareparts` (`id_sparepart`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stok_opnames`
--

LOCK TABLES `stok_opnames` WRITE;
/*!40000 ALTER TABLE `stok_opnames` DISABLE KEYS */;
INSERT INTO `stok_opnames` VALUES (4,'2025-08-30',1,100,90,-10,'',4,'2025-08-30 05:31:56','2025-08-30 05:31:56'),(5,'2025-08-30',1,90,100,10,'',4,'2025-08-30 05:32:53','2025-08-30 05:32:53'),(6,'2025-09-11',3,0,10,10,'',4,'2025-09-11 11:58:32','2025-09-11 11:58:32'),(7,'2025-09-11',2,80,80,0,'',4,'2025-09-11 11:58:32','2025-09-11 11:58:32'),(8,'2025-09-11',1,65,65,0,'',4,'2025-09-11 11:58:32','2025-09-11 11:58:32');
/*!40000 ALTER TABLE `stok_opnames` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `submerks`
--

DROP TABLE IF EXISTS `submerks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `submerks` (
  `id_submerk` int(11) NOT NULL AUTO_INCREMENT,
  `nama_submerk` varchar(50) NOT NULL,
  `id_merk` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_submerk`),
  KEY `merk_id` (`id_merk`),
  CONSTRAINT `submerks_ibfk_1` FOREIGN KEY (`id_merk`) REFERENCES `merks` (`id_merk`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `submerks`
--

LOCK TABLES `submerks` WRITE;
/*!40000 ALTER TABLE `submerks` DISABLE KEYS */;
INSERT INTO `submerks` VALUES (1,'djnsjdnkjs',1,'2025-08-30 04:28:14','2025-08-30 04:28:14'),(2,'dbjkjds',1,'2025-08-30 04:28:47','2025-08-30 04:28:47');
/*!40000 ALTER TABLE `submerks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `suppliers`
--

DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suppliers` (
  `id_supplier` int(11) NOT NULL AUTO_INCREMENT,
  `nama_supplier` varchar(100) NOT NULL,
  `alamat` text DEFAULT NULL,
  `telepon` varchar(20) DEFAULT NULL,
  `bengkel_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_supplier`),
  KEY `bengkel_id` (`bengkel_id`),
  CONSTRAINT `suppliers_ibfk_1` FOREIGN KEY (`bengkel_id`) REFERENCES `bengkels` (`id_bengkel`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suppliers`
--

LOCK TABLES `suppliers` WRITE;
/*!40000 ALTER TABLE `suppliers` DISABLE KEYS */;
INSERT INTO `suppliers` VALUES (1,'jdfnkjsnj','ndkjfndk','jdnfkjds',4,'2025-08-30 03:45:22','2025-08-30 03:45:22'),(2,'sjdfnnj','sdfnkjdnskj','sdnfkjnsdck',4,'2025-08-30 03:53:38','2025-08-30 03:53:38');
/*!40000 ALTER TABLE `suppliers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teknisis`
--

DROP TABLE IF EXISTS `teknisis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teknisis` (
  `id_teknisi` int(11) NOT NULL AUTO_INCREMENT,
  `nama_teknisi` varchar(100) NOT NULL,
  `telepon` varchar(20) DEFAULT NULL,
  `bengkel_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_teknisi`),
  KEY `bengkel_id` (`bengkel_id`),
  CONSTRAINT `teknisis_ibfk_1` FOREIGN KEY (`bengkel_id`) REFERENCES `bengkels` (`id_bengkel`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teknisis`
--

LOCK TABLES `teknisis` WRITE;
/*!40000 ALTER TABLE `teknisis` DISABLE KEYS */;
INSERT INTO `teknisis` VALUES (1,'jksdjks','jsdnjks',4,'2025-08-30 04:00:48','2025-08-30 04:00:48');
/*!40000 ALTER TABLE `teknisis` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transaksi`
--

DROP TABLE IF EXISTS `transaksi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transaksi` (
  `id_transaksi` int(11) NOT NULL AUTO_INCREMENT,
  `no_faktur` varchar(50) NOT NULL,
  `tanggal` datetime NOT NULL DEFAULT current_timestamp(),
  `jenis` enum('penjualan','pembelian') NOT NULL,
  `metode_bayar` varchar(50) NOT NULL,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(50,2) NOT NULL,
  `total_bayar` decimal(15,2) NOT NULL,
  `uang_bayar` decimal(15,2) NOT NULL,
  `kembalian` decimal(15,2) NOT NULL,
  `id_user` int(11) DEFAULT NULL,
  `id_supplier` int(11) DEFAULT NULL,
  `id_pelanggan` int(11) DEFAULT NULL,
  `id_teknisi` int(11) DEFAULT NULL,
  `kendaraan` varchar(50) DEFAULT NULL,
  `no_polisi` varchar(50) DEFAULT NULL,
  `id_bengkel` int(11) NOT NULL,
  `status` enum('selesai','pending') DEFAULT 'selesai',
  PRIMARY KEY (`id_transaksi`),
  UNIQUE KEY `no_faktur` (`no_faktur`),
  KEY `id_supplier` (`id_supplier`),
  KEY `id_pelanggan` (`id_pelanggan`),
  KEY `id_bengkel` (`id_bengkel`),
  CONSTRAINT `transaksi_ibfk_1` FOREIGN KEY (`id_supplier`) REFERENCES `suppliers` (`id_supplier`) ON DELETE SET NULL,
  CONSTRAINT `transaksi_ibfk_2` FOREIGN KEY (`id_pelanggan`) REFERENCES `pelanggans` (`id_pelanggan`) ON DELETE SET NULL,
  CONSTRAINT `transaksi_ibfk_3` FOREIGN KEY (`id_bengkel`) REFERENCES `bengkels` (`id_bengkel`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transaksi`
--

LOCK TABLES `transaksi` WRITE;
/*!40000 ALTER TABLE `transaksi` DISABLE KEYS */;
INSERT INTO `transaksi` VALUES (1,'PJ.20250906.8.4.0001','2025-09-07 15:13:04','penjualan','',40000.00,0.00,0.00,50000.00,10000.00,8,NULL,1,1,'Satria FU','Z01DF',4,'selesai'),(2,'PJ.20250906.8.4.0002','2025-09-16 18:18:47','penjualan','Tunai',22200.00,10.00,0.00,0.00,0.00,8,NULL,NULL,NULL,NULL,NULL,4,'selesai'),(3,'PJ.20250906.8.4.0003','2025-09-16 18:37:29','penjualan','Tunai',15650.00,5.00,14867.00,20000.00,0.00,8,NULL,NULL,NULL,NULL,NULL,4,'pending'),(4,'PJ.20250906.8.4.0004','2025-09-16 18:01:59','penjualan','',15650.00,10.00,0.00,15000.00,915.00,8,NULL,NULL,NULL,NULL,NULL,4,'pending'),(5,'PJ.20250906.8.4.0005','2025-09-06 16:42:41','penjualan','',910.00,0.00,0.00,0.00,0.00,8,NULL,NULL,1,'','',4,'pending'),(6,'PJ.20250911.8.4.0001','2025-09-11 19:37:54','penjualan','',25000.00,0.00,0.00,50000.00,25000.00,8,NULL,1,NULL,NULL,NULL,4,'selesai'),(7,'PJ.20250915.8.4.0001','2025-09-15 22:39:40','penjualan','',25000.00,0.00,0.00,50000.00,25000.00,8,NULL,1,NULL,NULL,NULL,4,'selesai'),(8,'PS.20250915.8.4.0002','2025-09-15 22:40:40','penjualan','',40000.00,0.00,0.00,50000.00,10000.00,8,NULL,1,1,'jsfdsj','knsdfjs',4,'selesai'),(9,'PJ.20250916.8.4.0001','2025-09-16 17:45:04','penjualan','Tunai',1300.00,2.00,0.00,2000.00,726.00,8,NULL,1,NULL,NULL,NULL,4,'selesai'),(10,'PS.20250916.8.4.0002','2025-09-16 17:57:03','penjualan','Non Tunai',15130.00,10.00,0.00,15000.00,1.38,8,NULL,NULL,NULL,NULL,NULL,4,'pending'),(11,'PJ.20250916.8.4.0006','2025-09-16 18:39:13','penjualan','Non Tunai',2600.00,5.00,2470.00,10000.00,7.53,8,NULL,NULL,NULL,NULL,NULL,4,'selesai'),(12,'PB.20250917.8.4.0001','2025-09-17 20:18:02','pembelian','Tunai',10010.00,0.00,0.00,0.00,0.00,8,NULL,NULL,NULL,NULL,NULL,4,'selesai'),(13,'PB.20250917.8.4.0002','2025-09-17 20:19:45','pembelian','Tunai',20000.00,0.00,0.00,0.00,0.00,8,NULL,NULL,NULL,NULL,NULL,4,'selesai'),(14,'PB.20250917.8.4.0003','2025-09-17 20:20:53','pembelian','Tunai',2000.00,0.00,0.00,0.00,0.00,8,NULL,NULL,NULL,NULL,NULL,4,'selesai'),(15,'PB.20250917.8.4.0004','2025-09-17 20:23:27','pembelian','Tunai',30000.00,0.00,0.00,0.00,0.00,8,1,NULL,NULL,NULL,NULL,4,'selesai'),(16,'PB.20250917.8.4.0005','2025-09-17 20:28:54','pembelian','Tunai',18000.00,0.00,0.00,0.00,0.00,8,1,NULL,NULL,NULL,NULL,4,'selesai'),(17,'PB.20250917.8.4.0006','2025-09-17 20:30:23','pembelian','Tunai',16000.00,0.00,0.00,0.00,0.00,8,1,NULL,NULL,NULL,NULL,4,'selesai');
/*!40000 ALTER TABLE `transaksi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transaksi_detail_servis`
--

DROP TABLE IF EXISTS `transaksi_detail_servis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transaksi_detail_servis` (
  `id_detail` int(11) NOT NULL AUTO_INCREMENT,
  `no_faktur` varchar(50) NOT NULL,
  `id_servis` varchar(50) NOT NULL,
  `nama_servis` varchar(100) NOT NULL,
  `biaya` decimal(15,2) NOT NULL,
  PRIMARY KEY (`id_detail`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transaksi_detail_servis`
--

LOCK TABLES `transaksi_detail_servis` WRITE;
/*!40000 ALTER TABLE `transaksi_detail_servis` DISABLE KEYS */;
INSERT INTO `transaksi_detail_servis` VALUES (7,'PJ.20250906.8.4.0001','1','Ganti Oli',15000.00),(8,'PJ.20250906.8.4.0002','1','Ganti Oli',15000.00),(9,'PJ.20250906.8.4.0003','1','Ganti Oli',15000.00),(10,'PJ.20250906.8.4.0004','1','Ganti Oli',15000.00),(11,'PJ.20250907.8.4.0001','1','Ganti Oli',15000.00),(12,'PS.20250915.8.4.0002','1','Ganti Oli',15000.00),(13,'PS.20250916.8.4.0002','1','Ganti Oli',15000.00);
/*!40000 ALTER TABLE `transaksi_detail_servis` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transaksi_detail_sparepart`
--

DROP TABLE IF EXISTS `transaksi_detail_sparepart`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transaksi_detail_sparepart` (
  `id_detail` int(11) NOT NULL AUTO_INCREMENT,
  `no_faktur` varchar(50) NOT NULL,
  `kode_sparepart` varchar(50) NOT NULL,
  `nama_sparepart` varchar(100) NOT NULL,
  `harga` decimal(15,2) NOT NULL,
  `qty` int(11) NOT NULL,
  `satuan` varchar(20) NOT NULL,
  `discount` decimal(15,2) NOT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  PRIMARY KEY (`id_detail`)
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transaksi_detail_sparepart`
--

LOCK TABLES `transaksi_detail_sparepart` WRITE;
/*!40000 ALTER TABLE `transaksi_detail_sparepart` DISABLE KEYS */;
INSERT INTO `transaksi_detail_sparepart` VALUES (36,'PJ.20250906.8.4.0001','4-SPART-357233986','jbvjsdbjsj',2500.00,10,'PCS',0.00,25000.00),(37,'PJ.20250906.8.4.0002','4-SPART-816110258','kjdbfkjdnk',100.00,20,'PCS',0.00,2000.00),(38,'PJ.20250906.8.4.0002','SP-1755528666','TEST',1300.00,4,'KARTON',0.00,5200.00),(39,'PJ.20250906.8.4.0003','SP-1755528666','TEST',130.00,5,'PCS',0.00,650.00),(40,'PJ.20250906.8.4.0004','SP-1755528666','TEST',130.00,5,'PCS',0.00,650.00),(41,'PJ.20250906.8.4.0005','SP-1755528666','TEST',130.00,7,'PCS',0.00,910.00),(42,'PJ.20250907.8.4.0001','SP-1755528666','TEST',130.00,14,'PCS',0.00,1820.00),(43,'PS.20250911.8.4.0001','4-SPART-357233986','jbvjsdbjsj',2500.00,1,'PCS',0.00,2500.00),(44,'PJ.20250911.8.4.0001','4-SPART-357233986','jbvjsdbjsj',25000.00,1,'KARTON',0.00,25000.00),(45,'PJ.20250915.8.4.0001','4-SPART-357233986','jbvjsdbjsj',25000.00,1,'KARTON',0.00,25000.00),(46,'PS.20250915.8.4.0002','4-SPART-357233986','jbvjsdbjsj',25000.00,1,'KARTON',0.00,25000.00),(47,'PJ.20250915.8.4.0003','4-SPART-816110258','kjdbfkjdnk',100.00,21,'PCS',0.00,2100.00),(48,'PJ.20250915.8.4.0003','4-SPART-357233986','jbvjsdbjsj',2500.00,5,'PCS',0.00,125000.00),(49,'PS.20250915.8.4.0003','4-SPART-357233986','jbvjsdbjsj',2500.00,1,'PCS',0.00,2500.00),(50,'PJ.20250916.8.4.0001','SP-1755528666','TEST',1300.00,1,'KARTON',0.00,1300.00),(51,'PS.20250916.8.4.0002','SP-1755528666','TEST',130.00,1,'PCS',0.00,130.00),(52,'PJ.20250916.8.4.0006','SP-1755528666','TEST',1300.00,2,'KARTON',0.00,2600.00),(53,'PB.20250917.8.4.0001','4-SPART-357233986','jbvjsdbjsj',25000.00,10,'KARTON',0.00,0.00),(54,'PJ.20250917.8.4.0001','SP-1755528666','TEST',1300.00,2,'KARTON',0.00,2600.00),(55,'PB.20250917.8.4.0001','4-SPART-741863857','dsjdncdjsk',10000.00,1,'PCS',0.00,10000.00),(56,'PB.20250917.8.4.0001','4-SPART-816110258','kjdbfkjdnk',10.00,1,'PCS',0.00,10.00),(57,'PB.20250917.8.4.0002','4-SPART-741863857','dsjdncdjsk',10000.00,2,'PCS',0.00,20000.00),(58,'PB.20250917.8.4.0003','4-SPART-357233986','jbvjsdbjsj',2000.00,1,'KARTON',0.00,2000.00),(59,'PB.20250917.8.4.0004','4-SPART-741863857','dsjdncdjsk',10000.00,3,'PCS',0.00,30000.00),(60,'PB.20250917.8.4.0005','4-SPART-357233986','jbvjsdbjsj',2000.00,9,'KARTON',0.00,18000.00),(61,'PB.20250917.8.4.0006','4-SPART-357233986','jbvjsdbjsj',2000.00,8,'KARTON',0.00,16000.00);
/*!40000 ALTER TABLE `transaksi_detail_sparepart` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id_user` int(11) NOT NULL AUTO_INCREMENT,
  `nama_lengkap` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('super_user','owner_bengkel','admin_bengkel','teknisi') NOT NULL,
  `bengkel_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `email` (`email`),
  KEY `bengkel_id` (`bengkel_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`bengkel_id`) REFERENCES `bengkels` (`id_bengkel`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Super User','superuser@gmail.com','$2y$10$nU6dZ53oxb26Ee8mKYjqIOWM72qQ73SNsho30CPwG1Cyl9yU7wcIi','super_user',NULL,'2025-08-17 13:13:27','2025-08-17 13:22:27'),(6,'Dede Hilman','dede@gmail.com','$2y$10$t8RN2DxI3/WFFJIoqgSTT.C0Em1yZ1gGUOx4UwJCG7SGMC/MEezO.','owner_bengkel',NULL,'2025-08-18 11:20:28','2025-08-30 06:02:28'),(7,'Rizal Cingir','rizalcingir@gmail.com','$2y$10$2nHniqIjqrqVsacrRxczO.UJe2mMxvrTDPQBb2xgX4TWOL5qtoO02','owner_bengkel',NULL,'2025-08-18 11:42:15','2025-08-18 11:42:15'),(8,'Santi Afriani','admin@gmail.com','$2y$10$f.qCTWfAU3cX/Y4kOxPei.OPawcEs3F9sE2pdCCQEyYt3HxkbGKzG','admin_bengkel',4,'2025-08-18 12:19:48','2025-08-18 12:20:44'),(9,'Bakri','teknisi@gmail.com','$2y$10$D2r3yoI3zKZKIna5fo/dkOm/YYNvUL4eZSg.lNq6Vd30Bpmpf8b.S','teknisi',4,'2025-08-18 12:24:14','2025-08-18 12:24:14');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-09-17 20:31:56
