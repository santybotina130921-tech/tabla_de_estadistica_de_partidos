-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 22-02-2026 a las 00:45:09
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `estadisticas_futbol`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `partidos_stats`
--

CREATE TABLE `partidos_stats` (
  `id` int(11) NOT NULL,
  `equipo_local` varchar(100) DEFAULT NULL,
  `equipo_visitante` varchar(100) DEFAULT NULL,
  `goles_favor_local` int(11) DEFAULT NULL,
  `goles_contra_local` int(11) DEFAULT NULL,
  `corners_promedio` float DEFAULT NULL,
  `tarjetas_amarillas` int(11) DEFAULT NULL,
  `tarjetas_rojas` int(11) DEFAULT NULL,
  `penaltis_favor` int(11) DEFAULT NULL,
  `atajadas_portero` int(11) DEFAULT NULL,
  `posesion_promedio` float DEFAULT NULL,
  `goleador_nombre` varchar(100) DEFAULT NULL,
  `probabilidad_ganar` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `partidos_stats`
--
ALTER TABLE `partidos_stats`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `partidos_stats`
--
ALTER TABLE `partidos_stats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
