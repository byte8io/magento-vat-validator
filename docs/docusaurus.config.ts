import { themes as prismThemes } from 'prism-react-renderer';
import type { Config } from '@docusaurus/types';
import type * as Preset from '@docusaurus/preset-classic';

const config: Config = {
  title: 'Byte8 VAT Validator',
  tagline: 'EU VIES + UK HMRC + Swiss UID validation for Magento 2',
  favicon: 'img/favicon.svg',

  future: {
    v4: true,
  },

  url: 'https://byte8.github.io',
  baseUrl: '/module-vat-validator/',

  organizationName: 'byte8',
  projectName: 'module-vat-validator',
  trailingSlash: false,

  onBrokenLinks: 'warn',

  markdown: {
    hooks: {
      onBrokenMarkdownLinks: 'warn',
    },
  },

  i18n: {
    defaultLocale: 'en',
    locales: ['en'],
  },

  presets: [
    [
      'classic',
      {
        docs: {
          sidebarPath: './sidebars.ts',
          routeBasePath: 'docs',
          editUrl:
            'https://github.com/byte8/module-vat-validator/edit/main/docs/',
        },
        blog: {
          showReadingTime: true,
          blogTitle: 'Changelog & updates',
          blogDescription: 'Release notes for Byte8 VAT Validator',
          postsPerPage: 10,
          feedOptions: {
            type: ['rss', 'atom'],
            xslt: true,
          },
          editUrl:
            'https://github.com/byte8/module-vat-validator/edit/main/docs/',
        },
        theme: {
          customCss: './src/css/custom.css',
        },
      } satisfies Preset.Options,
    ],
  ],

  themeConfig: {
    image: 'img/social-card.png',
    colorMode: {
      defaultMode: 'dark',
      disableSwitch: false,
      respectPrefersColorScheme: false,
    },
    navbar: {
      title: 'Byte8',
      logo: {
        alt: 'Byte8 VAT Validator',
        src: 'img/logo.svg',
        srcDark: 'img/logo.svg',
        width: 32,
        height: 32,
      },
      items: [
        {
          type: 'docSidebar',
          sidebarId: 'docsSidebar',
          position: 'left',
          label: 'Docs',
        },
        { to: '/blog', label: 'Changelog', position: 'left' },
        { to: '/pricing', label: 'Pricing', position: 'left' },
        {
          href: 'https://github.com/byte8/module-vat-validator',
          position: 'right',
          className: 'header-github-link',
          'aria-label': 'GitHub repository',
        },
        {
          href: 'https://byte8.io/magento-vat-validator',
          label: 'Get Started',
          position: 'right',
          className: 'navbar-cta-button',
        },
      ],
    },
    footer: {
      style: 'dark',
      logo: {
        alt: 'Byte8',
        src: 'img/logo.svg',
        href: 'https://byte8.io',
        width: 32,
        height: 32,
      },
      links: [
        {
          title: 'Docs',
          items: [
            { label: 'Quick start', to: '/docs/getting-started/quick-start' },
            { label: 'Configuration', to: '/docs/configuration/general' },
            { label: 'Validation log (DACH)', to: '/docs/validation-log/overview' },
            { label: 'REST API', to: '/docs/advanced/rest-api' },
          ],
        },
        {
          title: 'Resources',
          items: [
            { label: 'Changelog', to: '/blog' },
            { label: 'GitHub', href: 'https://github.com/byte8/module-vat-validator' },
            { label: 'Marketplace listing', href: 'https://commercemarketplace.adobe.com/' },
          ],
        },
        {
          title: 'Byte8',
          items: [
            { label: 'byte8.io', href: 'https://byte8.io' },
            { label: 'Byte8 Ledger (Sage / Xero sync)', href: 'https://byte8.io/ledger' },
            { label: 'Contact', href: 'mailto:helo@byte8.io' },
          ],
        },
      ],
      copyright: `© ${new Date().getFullYear()} Byte8 Ltd. MIT licensed.`,
    },
    prism: {
      theme: prismThemes.vsDark,
      darkTheme: prismThemes.vsDark,
      additionalLanguages: ['php', 'bash', 'json', 'xml-doc', 'tsx', 'sql'],
    },
  } satisfies Preset.ThemeConfig,
};

export default config;
