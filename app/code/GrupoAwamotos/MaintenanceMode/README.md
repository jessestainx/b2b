# GrupoAwamotos_MaintenanceMode

## Módulo de Modo de Manutenção Premium (Gratuito)

Este é um módulo de manutenção completo para Magento 2, com todas as funcionalidades de extensões pagas (como FME Extensions ~$99.99), mas 100% gratuito e personalizado para AWA Motos.

---

## 🎯 Funcionalidades

### Modos de Operação
- **Online**: Site funcionando normalmente
- **Manutenção**: Página de manutenção com código HTTP 503
- **Em Breve (Coming Soon)**: Página de lançamento com código HTTP 200

### Controle de Acesso
- ✅ **Código Secreto via URL**: `seusite.com/?preview=CODIGO`
- ✅ **Código Secreto via Formulário**: Na própria página de manutenção
- ✅ **Whitelist de IPs**: Lista de IPs que podem acessar normalmente
- ✅ **Cookie de Acesso**: Duração configurável (padrão 72h)
- ✅ **Páginas CMS Permitidas**: Acesso a páginas específicas (ex: FAQ, Contato)
- ✅ **Rotas Permitidas**: Rotas do Magento acessíveis (ex: newsletter, contact)

### Design e Personalização
- ✅ **Upload de Logo**: Imagem personalizada
- ✅ **Tipos de Fundo**: Cor sólida, Gradiente, Imagem ou Vídeo
- ✅ **Vídeo de Fundo**: Suporte a MP4 e YouTube
- ✅ **Cores Personalizadas**: Texto e fundo
- ✅ **CSS Customizado**: Para ajustes avançados

### Funcionalidades Extras
- ✅ **Contador Regressivo**: Com data/hora configurável
- ✅ **Newsletter**: Formulário de inscrição integrado ao Magento
- ✅ **Redes Sociais**: Links para Facebook, Instagram, YouTube e WhatsApp
- ✅ **Informações de Contato**: Telefone, WhatsApp e E-mail
- ✅ **Design Responsivo**: Funciona em mobile e desktop
- ✅ **Animações CSS**: Entrada suave e efeitos visuais

---

## 📋 Configuração no Admin

1. Acesse: **Stores > Configuration > AWA Motos > Modo de Manutenção**

### Grupos de Configuração

| Grupo | Descrição |
|-------|-----------|
| ⚙️ Configurações Gerais | Ativar/desativar, modo, código secreto, IPs permitidos |
| 🔧 Página de Manutenção | Título, mensagem, contador |
| 🚀 Página "Em Breve" | Título, mensagem, contador |
| 🎨 Design e Aparência | Logo, fundo, cores, CSS |
| 📧 Newsletter | Formulário de inscrição |
| 🌐 Redes Sociais | Links sociais |
| 📞 Contato | Informações de contato |

---

## 🔑 Formas de Acesso Durante Manutenção

### 1. Via URL (Recomendado para desenvolvedores)
```
https://awamotos.com/?preview=SEU_CODIGO_SECRETO
```
Após acessar com o código, um cookie é definido e você pode navegar normalmente por 72h.

### 2. Via Formulário na Página
Na página de manutenção, clique em "🔐 Possui código de acesso?" e digite o código.

### 3. Via IP Whitelist
Configure IPs permitidos no admin (um por linha).

---

## 📁 Estrutura do Módulo

```
app/code/GrupoAwamotos/MaintenanceMode/
├── registration.php
├── etc/
│   ├── module.xml
│   ├── config.xml                 # Valores padrão
│   ├── acl.xml                    # Permissões
│   ├── adminhtml/
│   │   └── system.xml             # Configurações do admin
│   └── frontend/
│       ├── events.xml             # Observer para interceptar requests
│       └── routes.xml             # Rotas para controllers
├── Block/
│   └── Adminhtml/System/Config/
│       └── Editor.php             # Editor WYSIWYG
├── Controller/
│   ├── Access/
│   │   └── Validate.php           # Validação de código secreto
│   └── Newsletter/
│       └── Subscribe.php          # Inscrição newsletter
├── Model/
│   └── Config/Source/
│       ├── Mode.php               # Modos (Online/Coming Soon/Maintenance)
│       └── BackgroundType.php     # Tipos de fundo
└── Observer/
    └── MaintenanceCheck.php       # Lógica principal
```

---

## 🚀 Uso Rápido

### Ativar Modo de Manutenção
1. Vá em Stores > Configuration > AWA Motos > Modo de Manutenção
2. Em "Configurações Gerais", ative "Ativar Modo de Manutenção"
3. Escolha o Modo: "Manutenção" ou "Em Breve"
4. Configure seu código secreto
5. Salve e limpe o cache

### Testar Antes de Ativar
1. Configure o código secreto
2. Acesse `seusite.com/?preview=SEU_CODIGO`
3. Navegue normalmente com o cookie ativo

---

## 🎨 Valores Padrão Configurados

| Configuração | Valor |
|--------------|-------|
| Código Secreto | `awa2025` |
| Duração do Cookie | 72 horas |
| Tipo de Fundo | Gradiente |
| Gradiente | #1a237e → #000428 |
| Cor do Texto | #ffffff |
| Facebook | https://facebook.com/awamotos |
| Instagram | https://instagram.com/awamotos |
| YouTube | https://youtube.com/@awamotos7661 |
| WhatsApp | 5516997367588 |
| Telefone | (16) 3301-1890 |
| E-mail | awamotos@awamotos.com.br |

---

## 🔧 Comandos Úteis

```bash
# Limpar cache após alterar configurações
php bin/magento cache:flush

# Verificar se o módulo está ativo
php bin/magento module:status GrupoAwamotos_MaintenanceMode

# Recompilar após alterações no código
php bin/magento setup:di:compile
```

---

## 📊 Comparativo com Extensões Pagas

| Funcionalidade | FME ($99.99) | Este Módulo (Grátis) |
|----------------|--------------|----------------------|
| Modo Manutenção | ✅ | ✅ |
| Coming Soon | ✅ | ✅ |
| Contador Regressivo | ✅ | ✅ |
| Newsletter | ✅ | ✅ |
| Upload de Logo | ✅ | ✅ |
| Vídeo de Fundo | ✅ | ✅ |
| Código Secreto URL | ✅ | ✅ |
| Código Secreto Form | ❌ | ✅ |
| IP Whitelist | ✅ | ✅ |
| Páginas CMS Permitidas | ✅ | ✅ |
| Redes Sociais | ✅ | ✅ |
| Design Responsivo | ✅ | ✅ |
| Personalizado AWA Motos | ❌ | ✅ |
| **Preço** | **$99.99** | **Grátis** |

---

## 🏢 Desenvolvido para AWA Motos

**AWA Motos** - Peças e Acessórios para Motos
- 📍 R. Lavineo de Arruda Falcão, 1272, Jardim Cruzeiro do Sul
- 📍 CEP 14808-390 - Araraquara/SP
- 📞 (16) 3301-1890
- 💬 (16) 99736-7588
- ✉️ awamotos@awamotos.com.br
- 📋 CNPJ: 51.274.901/0001-02

---

**Versão**: 1.0.0  
**Compatibilidade**: Magento 2.4.x  
**Data**: Dezembro 2025
