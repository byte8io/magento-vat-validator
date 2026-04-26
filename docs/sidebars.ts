import type { SidebarsConfig } from '@docusaurus/plugin-content-docs';

const sidebars: SidebarsConfig = {
  docsSidebar: [
    'intro',
    {
      type: 'category',
      label: 'Getting started',
      collapsed: false,
      items: [
        'getting-started/quick-start',
        'getting-started/installation',
        'getting-started/first-validation',
      ],
    },
    {
      type: 'category',
      label: 'Configuration',
      items: [
        'configuration/general',
        'configuration/customer-groups',
        'configuration/upstreams',
      ],
    },
    {
      type: 'category',
      label: 'Validation Log (DACH)',
      items: [
        'validation-log/overview',
        'validation-log/admin-grid',
        'validation-log/csv-export',
        'validation-log/retention',
      ],
    },
    {
      type: 'category',
      label: 'Upstream clients',
      items: [
        'clients/vies',
        'clients/hmrc',
        'clients/uid-che',
      ],
    },
    {
      type: 'category',
      label: 'Front-end',
      items: [
        'frontend/hyva',
        'frontend/velafront',
      ],
    },
    {
      type: 'category',
      label: 'Advanced',
      items: [
        'advanced/rest-api',
        'advanced/cli',
        'advanced/events',
        'advanced/privacy-gdpr',
        'advanced/troubleshooting',
      ],
    },
  ],
};

export default sidebars;
