-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 29/11/2025 às 18:52
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `neuroblogs`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `comentarios_comunidade`
--

CREATE TABLE `comentarios_comunidade` (
  `id` int(11) NOT NULL,
  `id_postagem` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `conteudo` text NOT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `comentarios_comunidade`
--

INSERT INTO `comentarios_comunidade` (`id`, `id_postagem`, `id_usuario`, `conteudo`, `data_criacao`) VALUES
(1, 2, 1, 'sadas', '2025-11-28 14:29:08'),
(2, 2, 1, 'sdsad', '2025-11-28 14:34:55'),
(3, 3, 1, 'sss', '2025-11-28 14:43:57'),
(4, 3, 1, 'sda', '2025-11-28 14:51:42'),
(5, 2, 1, 'sdsad', '2025-11-28 15:00:48'),
(6, 9, 1, 'sdsad', '2025-11-28 18:05:09'),
(7, 15, 1, 'sss', '2025-11-28 18:36:31'),
(8, 23, 5, '12', '2025-11-29 14:22:09'),
(9, 23, 5, 'oi', '2025-11-29 14:30:10'),
(13, 71, 5, 'teste12', '2025-11-29 15:18:42'),
(14, 72, 5, 'oi13', '2025-11-29 15:18:57'),
(15, 71, 5, 'oi12', '2025-11-29 15:21:24'),
(16, 71, 5, '123123', '2025-11-29 15:23:05'),
(17, 71, 5, 'oi', '2025-11-29 15:24:22'),
(18, 73, 5, 'asd', '2025-11-29 15:33:36'),
(19, 73, 5, '123', '2025-11-29 15:33:47'),
(20, 73, 5, 'oi', '2025-11-29 15:37:46'),
(21, 73, 5, 'oi1', '2025-11-29 16:03:29'),
(22, 21, 5, 'oi', '2025-11-29 16:16:22'),
(23, 21, 5, '12', '2025-11-29 16:16:37'),
(24, 73, 5, 'oi123', '2025-11-29 16:25:26'),
(25, 73, 5, 'oi12334', '2025-11-29 16:58:58'),
(26, 73, 7, 'oi', '2025-11-29 17:04:36'),
(27, 73, 7, '12', '2025-11-29 17:04:49'),
(28, 74, 7, 'oi', '2025-11-29 17:19:22'),
(29, 74, 7, '.', '2025-11-29 17:21:05'),
(30, 74, 7, '123', '2025-11-29 17:33:08'),
(31, 75, 7, '123', '2025-11-29 17:33:18'),
(32, 75, 7, '123', '2025-11-29 17:33:20'),
(33, 75, 7, '321', '2025-11-29 17:33:21');

-- --------------------------------------------------------

--
-- Estrutura para tabela `comentarios_pessoais`
--

CREATE TABLE `comentarios_pessoais` (
  `id` int(11) NOT NULL,
  `id_postagem` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `conteudo` text NOT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `comentarios_pessoais`
--

INSERT INTO `comentarios_pessoais` (`id`, `id_postagem`, `id_usuario`, `conteudo`, `data_criacao`) VALUES
(1, 1, 1, 'dsadsad', '2025-11-28 14:48:58'),
(2, 3, 1, 'KKK', '2025-11-28 15:41:25'),
(3, 4, 1, 'adsa', '2025-11-28 17:04:06'),
(4, 2, 1, 'dsad', '2025-11-28 18:10:24'),
(5, 5, 1, 'cxcx', '2025-11-28 18:15:11');

-- --------------------------------------------------------

--
-- Estrutura para tabela `comunidades`
--

CREATE TABLE `comunidades` (
  `id` int(11) NOT NULL,
  `nome_comunidade` varchar(100) NOT NULL,
  `tema_principal` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `imagem` varchar(255) DEFAULT 'uploads/comunidade/default.png',
  `id_criador` int(11) NOT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `comunidades`
--

INSERT INTO `comunidades` (`id`, `nome_comunidade`, `tema_principal`, `descricao`, `imagem`, `id_criador`, `data_criacao`) VALUES
(1, 'fds', '', 'dfd', 'uploads/comunidade/default.png', 1, '2025-11-28 14:27:30'),
(2, 'ZXCZ\\CX', '', 'sdsadasd', 'uploads/comunidade/default.png', 1, '2025-11-28 15:45:33'),
(3, 'fghfh', '', 'dsafdsaf', 'uploads/comunidade/default.png', 1, '2025-11-28 15:45:46'),
(4, 'aaa', '', 'a', 'uploads/comunidade/default.png', 1, '2025-11-28 15:45:54'),
(5, 'e', '', 'e', 'uploads/comunidade/default.png', 1, '2025-11-28 15:46:03'),
(6, 'kkk', '', 'sdsds', 'uploads/comunidade/default.png', 1, '2025-11-28 18:43:30'),
(7, 'Tecnologia', '', 'tecnologia', 'uploads/comunidade/default.png', 7, '2025-11-29 17:18:06');

-- --------------------------------------------------------

--
-- Estrutura para tabela `curtidas_comunidade`
--

CREATE TABLE `curtidas_comunidade` (
  `id` int(11) NOT NULL,
  `id_postagem` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `curtidas_comunidade`
--

INSERT INTO `curtidas_comunidade` (`id`, `id_postagem`, `id_usuario`) VALUES
(2, 3, 1),
(3, 7, 1),
(4, 8, 1),
(5, 9, 1),
(6, 15, 1),
(7, 18, 1),
(9, 20, 1),
(37, 21, 5),
(17, 23, 5),
(35, 71, 5),
(45, 73, 5),
(74, 73, 7),
(75, 74, 7),
(79, 75, 7);

-- --------------------------------------------------------

--
-- Estrutura para tabela `curtidas_pessoais`
--

CREATE TABLE `curtidas_pessoais` (
  `id` int(11) NOT NULL,
  `id_postagem` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `curtidas_pessoais`
--

INSERT INTO `curtidas_pessoais` (`id`, `id_postagem`, `id_usuario`) VALUES
(1, 1, 1),
(3, 2, 1),
(2, 3, 1),
(5, 4, 1),
(6, 5, 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `membros_comunidade`
--

CREATE TABLE `membros_comunidade` (
  `id_comunidade` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `data_entrada` timestamp NOT NULL DEFAULT current_timestamp(),
  `cargo` varchar(50) NOT NULL DEFAULT 'Membro'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `membros_comunidade`
--

INSERT INTO `membros_comunidade` (`id_comunidade`, `id_usuario`, `is_admin`, `data_entrada`, `cargo`) VALUES
(1, 1, 0, '2025-11-28 18:18:36', 'Membro'),
(2, 1, 1, '2025-11-28 15:45:33', 'Membro'),
(3, 1, 1, '2025-11-28 15:45:46', 'Membro'),
(4, 1, 0, '2025-11-28 18:50:16', 'Membro'),
(4, 2, 0, '2025-11-28 19:07:59', 'Membro'),
(4, 5, 0, '2025-11-29 14:20:37', 'Membro'),
(4, 7, 0, '2025-11-29 17:15:53', 'Membro'),
(5, 1, 0, '2025-11-28 15:46:09', 'Membro'),
(5, 5, 0, '2025-11-29 14:57:51', 'Membro'),
(6, 1, 1, '2025-11-28 18:43:30', 'Membro'),
(6, 2, 0, '2025-11-28 19:03:53', 'Membro'),
(7, 7, 0, '2025-11-29 17:19:06', 'Membro');

-- --------------------------------------------------------

--
-- Estrutura para tabela `perfil_usuario`
--

CREATE TABLE `perfil_usuario` (
  `id` int(11) NOT NULL COMMENT 'Chave estrangeira para usuarios.id',
  `pronoun` varchar(50) DEFAULT NULL,
  `neurotipos` varchar(100) DEFAULT NULL,
  `bio` text DEFAULT NULL COMMENT 'Biografia consolidada do usuário',
  `foto_perfil` varchar(255) NOT NULL DEFAULT 'imagens/default.png',
  `cor_fundo_pref` varchar(7) NOT NULL DEFAULT '#FFFFFF',
  `cor_texto_pref` varchar(7) NOT NULL DEFAULT '#374151',
  `tamanho_fonte_pref` varchar(10) NOT NULL DEFAULT 'medium',
  `fonte_preferida` varchar(50) NOT NULL DEFAULT 'sans-serif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `perfil_usuario`
--

INSERT INTO `perfil_usuario` (`id`, `pronoun`, `neurotipos`, `bio`, `foto_perfil`, `cor_fundo_pref`, `cor_texto_pref`, `tamanho_fonte_pref`, `fonte_preferida`) VALUES
(1, NULL, NULL, '', 'uploads/perfil/1_1764425778.png', '#FFFFFF', '#374151', 'medium', 'sans-serif'),
(2, NULL, NULL, '', 'uploads/perfil/2_1764356200.png', '#FFFFFF', '#374151', 'medium', 'sans-serif');

-- --------------------------------------------------------

--
-- Estrutura para tabela `posts_comunidade`
--

CREATE TABLE `posts_comunidade` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL COMMENT 'Quem postou',
  `id_comunidade` int(11) NOT NULL COMMENT 'Obrigatorio - Garante que está no feed principal',
  `titulo` varchar(255) NOT NULL,
  `conteudo` text NOT NULL,
  `imagem` varchar(255) DEFAULT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `formato` varchar(50) NOT NULL DEFAULT 'somente-texto',
  `tipo_analise` varchar(50) NOT NULL DEFAULT 'analise-aprofundada',
  `aviso_sensibilidade` varchar(50) NOT NULL DEFAULT 'sem-spoiler'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `posts_comunidade`
--

INSERT INTO `posts_comunidade` (`id`, `usuario_id`, `id_comunidade`, `titulo`, `conteudo`, `imagem`, `data_criacao`, `formato`, `tipo_analise`, `aviso_sensibilidade`) VALUES
(2, 1, 1, '', '', NULL, '2025-11-28 14:29:04', 'somente-texto', 'analise-aprofundada', 'sem-spoiler'),
(3, 1, 1, '', 'xdxxd', NULL, '2025-11-28 14:42:10', 'somente-texto', 'analise-aprofundada', 'sem-spoiler'),
(4, 1, 1, 'fdsaf', 'sfsa', NULL, '2025-11-28 15:26:22', 'somente-texto', 'analise-aprofundada', 'sem-spoiler'),
(5, 1, 1, 'JNJ', 'JHJHJ', NULL, '2025-11-28 15:40:38', 'somente-texto', 'analise-aprofundada', 'sem-spoiler'),
(6, 1, 1, 'HJHJ', 'HJJH', NULL, '2025-11-28 15:40:42', 'somente-texto', 'analise-aprofundada', 'sem-spoiler'),
(7, 1, 1, 'HJHJHJ', 'HJHJ', NULL, '2025-11-28 15:40:47', 'somente-texto', 'analise-aprofundada', 'sem-spoiler'),
(8, 1, 1, 'HJHJ', 'HJJH', NULL, '2025-11-28 15:41:03', 'somente-texto', 'analise-aprofundada', 'sem-spoiler'),
(9, 1, 5, 'sadas', 'dsad', NULL, '2025-11-28 18:05:03', 'somente-texto', 'analise-aprofundada', 'sem-spoiler'),
(10, 1, 5, 'sadas', 'dsad', NULL, '2025-11-28 18:05:11', 'somente-texto', 'analise-aprofundada', 'sem-spoiler'),
(11, 1, 5, 'sds', 'dsd', NULL, '2025-11-28 18:11:26', 'somente-texto', 'analise-aprofundada', 'sem-spoiler'),
(12, 1, 5, 'sss', 'sssssssssss', NULL, '2025-11-28 18:11:32', 'somente-texto', 'analise-aprofundada', 'sem-spoiler'),
(13, 1, 4, 'sas', 'asa', NULL, '2025-11-28 18:20:36', 'somente-texto', 'analise-aprofundada', 'sem-spoiler'),
(14, 1, 4, 'wwe', 'dddddddd', 'uploads/posts/post_comunidade_6929eb2bb7df3.png', '2025-11-28 18:34:19', 'somente-texto', 'analise-aprofundada', 'sem-spoiler'),
(15, 1, 4, 'ss', 'ss', 'uploads/posts/post_comunidade_6929eb3d821e8.png', '2025-11-28 18:34:37', 'somente-texto', 'analise-aprofundada', 'sem-spoiler'),
(16, 1, 4, 'ss', 'ss', 'uploads/posts/post_comunidade_6929eba456e2f.png', '2025-11-28 18:36:20', 'somente-texto', 'analise-aprofundada', 'sem-spoiler'),
(17, 1, 4, 'ss', 'ss', 'uploads/posts/post_comunidade_6929ebb187a01.png', '2025-11-28 18:36:33', 'somente-texto', 'analise-aprofundada', 'sem-spoiler'),
(18, 1, 4, '', 'dfdf', NULL, '2025-11-28 18:43:15', 'somente-texto', 'analise-aprofundada', 'sem-spoiler'),
(19, 1, 1, '', 'p', NULL, '2025-11-28 18:53:33', 'somente-texto', 'analise-aprofundada', 'sem-spoiler'),
(20, 1, 4, '', '', 'uploads/posts/post_692aff682e3e0.webp', '2025-11-29 14:12:56', 'somente-texto', 'analise-aprofundada', 'sem-spoiler'),
(21, 1, 4, '', 'Testeeeee', 'uploads/posts/post_692aff8e6bdcb.webp', '2025-11-29 14:13:34', 'somente-texto', 'analise-aprofundada', 'sem-spoiler'),
(22, 1, 4, '', 'teste2', NULL, '2025-11-29 14:14:42', 'somente-texto', 'analise-aprofundada', 'sem-spoiler'),
(23, 1, 4, '', '', NULL, '2025-11-29 14:14:48', 'somente-texto', 'analise-aprofundada', 'sem-spoiler'),
(71, 5, 4, '', 'oi12', NULL, '2025-11-29 15:16:23', 'somente-texto', 'analise-aprofundada', 'sem-spoiler'),
(72, 5, 5, '', 'oi13', NULL, '2025-11-29 15:18:30', 'somente-texto', 'analise-aprofundada', 'sem-spoiler'),
(73, 5, 4, '', 'oi123', NULL, '2025-11-29 15:27:30', 'somente-texto', 'analise-aprofundada', 'sem-spoiler'),
(74, 7, 4, '', 'tecnologiaw', 'uploads/posts_comunidade/post_692b2afabb381.webp', '2025-11-29 17:18:50', 'somente-texto', 'analise-aprofundada', 'sem-spoiler'),
(75, 7, 4, '', '123', NULL, '2025-11-29 17:33:15', 'somente-texto', 'analise-aprofundada', 'sem-spoiler');

-- --------------------------------------------------------

--
-- Estrutura para tabela `posts_pessoais`
--

CREATE TABLE `posts_pessoais` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL COMMENT 'Obrigatorio - O dono do post',
  `titulo` varchar(255) DEFAULT NULL,
  `conteudo` text NOT NULL,
  `imagem` varchar(255) DEFAULT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `posts_pessoais`
--

INSERT INTO `posts_pessoais` (`id`, `usuario_id`, `titulo`, `conteudo`, `imagem`, `data_criacao`) VALUES
(1, 1, NULL, 'dsad', NULL, '2025-11-28 14:35:14'),
(2, 1, NULL, 'asdsdsad', NULL, '2025-11-28 14:49:01'),
(3, 1, NULL, 'sdsadsad', NULL, '2025-11-28 14:49:08'),
(4, 1, NULL, 'KKK', NULL, '2025-11-28 15:41:40'),
(5, 1, NULL, 'cxzczx', NULL, '2025-11-28 18:15:06'),
(6, 1, NULL, 'cxzczx', NULL, '2025-11-28 18:15:20'),
(7, 1, NULL, 'cxzczx', NULL, '2025-11-28 18:15:38');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(10) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(100) NOT NULL,
  `apelido` varchar(50) NOT NULL,
  `nivel` int(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `email`, `senha`, `apelido`, `nivel`) VALUES
(1, 'ak@gmail.com', '$2y$10$mJT.XRnXSg2DbgSLIfBhWeYfNwL3emH7.qTzyCwiT8v/VTw/NyY/O', 'akiP', 0),
(2, 'a@gmail.com', '$2y$10$yUjyCyt1VXzZAk/StjtGGO4fiq5xLa3Z0z1WKFohNVf10ZPOkN5Iy', 'a', 1),
(3, 'k@gmail.com', '$2y$10$fai1Xh247nTEjeJOg9xJgebxzhm881BdD/MsKEFHCLNm3yf.5VP5K', 'k', 0),
(4, 's@gmail', '$2y$10$06JPfyeiQiKcVNoTTGNLW.o.WQg92g9VMxD9cJ3O76cdWzBNFWqYu', 's', 0),
(5, 'luan@gmail.com', '$2y$10$sI1.uVCbTX/vya4GTHF6t.UxKROVhNMLniRxnQgt7KqJzp.KG/uRC', 'luan', 0),
(6, 'oi1@gmail.com', '$2y$10$29NrN7IUkxyIQFKgBTXDCut9.RMZyX8xMh1vmsfcUrCO5E8NLrcK2', 'oi1', 0),
(7, 'oi2@gmail.com', '$2y$10$T1mYvwR8QRmJq6LqOv0ceuXY.GMiMG8.Ot/NtdJDuM9mLLDtUyDK.', 'oi2', 0);

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `comentarios_comunidade`
--
ALTER TABLE `comentarios_comunidade`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_comentarios_post_com` (`id_postagem`),
  ADD KEY `fk_comentarios_user_com` (`id_usuario`);

--
-- Índices de tabela `comentarios_pessoais`
--
ALTER TABLE `comentarios_pessoais`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_comentarios_post_pes` (`id_postagem`),
  ADD KEY `fk_comentarios_user_pes` (`id_usuario`);

--
-- Índices de tabela `comunidades`
--
ALTER TABLE `comunidades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome_comunidade` (`nome_comunidade`),
  ADD KEY `id_criador` (`id_criador`);

--
-- Índices de tabela `curtidas_comunidade`
--
ALTER TABLE `curtidas_comunidade`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uc_post_user_com` (`id_postagem`,`id_usuario`),
  ADD KEY `fk_curtidas_user_com` (`id_usuario`);

--
-- Índices de tabela `curtidas_pessoais`
--
ALTER TABLE `curtidas_pessoais`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uc_post_user_pes` (`id_postagem`,`id_usuario`),
  ADD KEY `fk_curtidas_user_pes` (`id_usuario`);

--
-- Índices de tabela `membros_comunidade`
--
ALTER TABLE `membros_comunidade`
  ADD PRIMARY KEY (`id_comunidade`,`id_usuario`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Índices de tabela `perfil_usuario`
--
ALTER TABLE `perfil_usuario`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `posts_comunidade`
--
ALTER TABLE `posts_comunidade`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `fk_comunidade` (`id_comunidade`);

--
-- Índices de tabela `posts_pessoais`
--
ALTER TABLE `posts_pessoais`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `comentarios_comunidade`
--
ALTER TABLE `comentarios_comunidade`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT de tabela `comentarios_pessoais`
--
ALTER TABLE `comentarios_pessoais`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `comunidades`
--
ALTER TABLE `comunidades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `curtidas_comunidade`
--
ALTER TABLE `curtidas_comunidade`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT de tabela `curtidas_pessoais`
--
ALTER TABLE `curtidas_pessoais`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `posts_comunidade`
--
ALTER TABLE `posts_comunidade`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT de tabela `posts_pessoais`
--
ALTER TABLE `posts_pessoais`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `comentarios_comunidade`
--
ALTER TABLE `comentarios_comunidade`
  ADD CONSTRAINT `fk_comentarios_post_com` FOREIGN KEY (`id_postagem`) REFERENCES `posts_comunidade` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_comentarios_user_com` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `comentarios_pessoais`
--
ALTER TABLE `comentarios_pessoais`
  ADD CONSTRAINT `fk_comentarios_post_pes` FOREIGN KEY (`id_postagem`) REFERENCES `posts_pessoais` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_comentarios_user_pes` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `comunidades`
--
ALTER TABLE `comunidades`
  ADD CONSTRAINT `fk_comunidades_criador` FOREIGN KEY (`id_criador`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `curtidas_comunidade`
--
ALTER TABLE `curtidas_comunidade`
  ADD CONSTRAINT `fk_curtidas_post_com` FOREIGN KEY (`id_postagem`) REFERENCES `posts_comunidade` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_curtidas_user_com` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `curtidas_pessoais`
--
ALTER TABLE `curtidas_pessoais`
  ADD CONSTRAINT `fk_curtidas_post_pes` FOREIGN KEY (`id_postagem`) REFERENCES `posts_pessoais` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_curtidas_user_pes` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `membros_comunidade`
--
ALTER TABLE `membros_comunidade`
  ADD CONSTRAINT `fk_membros_comunidade` FOREIGN KEY (`id_comunidade`) REFERENCES `comunidades` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_membros_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `perfil_usuario`
--
ALTER TABLE `perfil_usuario`
  ADD CONSTRAINT `fk_perfil_usuario` FOREIGN KEY (`id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `posts_comunidade`
--
ALTER TABLE `posts_comunidade`
  ADD CONSTRAINT `fk_posts_comunidade_comunidade` FOREIGN KEY (`id_comunidade`) REFERENCES `comunidades` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_posts_comunidade_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `posts_pessoais`
--
ALTER TABLE `posts_pessoais`
  ADD CONSTRAINT `fk_posts_pessoais_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
