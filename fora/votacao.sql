-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 14/10/2025 às 05:33
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `votacao`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `departamentos`
--

CREATE TABLE `departamentos` (
  `id` int(11) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `lider_escolhido_2026` varchar(100) DEFAULT NULL,
  `indicados` text DEFAULT '[]',
  `votos_json` text DEFAULT NULL,
  `status_votacao` tinyint(1) DEFAULT 0,
  `votos_lider_json` text DEFAULT '[]',
  `votos_contagem` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '{}' CHECK (json_valid(`votos_contagem`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `departamentos`
--

INSERT INTO `departamentos` (`id`, `nome`, `lider_escolhido_2026`, `indicados`, `votos_json`, `status_votacao`, `votos_lider_json`, `votos_contagem`) VALUES
(1, 'Tesouraria / Finanças', NULL, '[]', NULL, 0, '[]', '{}'),
(2, 'Ministério Pessoal e Evangelismo', NULL, '[]', NULL, 0, '[]', '{}'),
(3, 'Escola Sabatina / Educação Cristã', NULL, '[]', NULL, 0, '[]', '{}'),
(4, 'Adventistas Jovens', NULL, '[]', NULL, 0, '[]', '{}'),
(5, 'Ministério da Mulher', NULL, '[]', NULL, 0, '[]', '{}'),
(6, 'Ministério da Família', NULL, '[]', NULL, 0, '[]', '{}'),
(7, 'Saúde', NULL, '[]', NULL, 0, '[]', '{}'),
(8, 'Mordomia Cristã', NULL, '[]', NULL, 0, '[]', '{}'),
(9, 'Música e Adoração / Louvor', NULL, '[]', NULL, 0, '[]', '{}'),
(10, 'Comunicação e Tecnologia', NULL, '[]', NULL, 0, '[]', '{}'),
(11, 'Secretaria / Administração Geral', NULL, '[]', NULL, 0, '[]', '{}');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `online` tinyint(1) DEFAULT 0,
  `tipo` enum('admin','normal') DEFAULT 'normal'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `email`, `senha`, `online`, `tipo`) VALUES
(1, 'Sloan', 'sloan@teste.com', '$2y$10$e0NRzZvl4uf06dL1sK2ceO6xv4FOPt3hD7qXg6f/Uf/Z0k/4J1m3a', 0, 'admin'),
(2, 'Ana Silva', 'ana@teste.com', '$2y$10$e0NRzZvl4uf06dL1sK2ceO6xv4FOPt3hD7qXg6f/Uf/Z0k/4J1m3a', 0, 'normal'),
(3, 'Bruno Costa', 'bruno@teste.com', '$2y$10$e0NRzZvl4uf06dL1sK2ceO6xv4FOPt3hD7qXg6f/Uf/Z0k/4J1m3a', 0, 'normal'),
(4, 'Carla Souza', 'carla@teste.com', '$2y$10$e0NRzZvl4uf06dL1sK2ceO6xv4FOPt3hD7qXg6f/Uf/Z0k/4J1m3a', 0, 'normal'),
(5, 'Diego Ferreira', 'diego@teste.com', '$2y$10$e0NRzZvl4uf06dL1sK2ceO6xv4FOPt3hD7qXg6f/Uf/Z0k/4J1m3a', 0, 'normal'),
(6, 'Eduarda Lima', 'eduarda@teste.com', '$2y$10$e0NRzZvl4uf06dL1sK2ceO6xv4FOPt3hD7qXg6f/Uf/Z0k/4J1m3a', 0, 'normal'),
(7, 'Felipe Mendes', 'felipe@teste.com', '$2y$10$e0NRzZvl4uf06dL1sK2ceO6xv4FOPt3hD7qXg6f/Uf/Z0k/4J1m3a', 0, 'normal'),
(8, 'Gabriela Rocha', 'gabriela@teste.com', '$2y$10$e0NRzZvl4uf06dL1sK2ceO6xv4FOPt3hD7qXg6f/Uf/Z0k/4J1m3a', 0, 'normal'),
(9, 'Henrique Alves', 'henrique@teste.com', '$2y$10$e0NRzZvl4uf06dL1sK2ceO6xv4FOPt3hD7qXg6f/Uf/Z0k/4J1m3a', 0, 'normal'),
(10, 'Isabela Martins', 'isabela@teste.com', '$2y$10$e0NRzZvl4uf06dL1sK2ceO6xv4FOPt3hD7qXg6f/Uf/Z0k/4J1m3a', 0, 'normal');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `departamentos`
--
ALTER TABLE `departamentos`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `departamentos`
--
ALTER TABLE `departamentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
