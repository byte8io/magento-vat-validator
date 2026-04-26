import Link from '@docusaurus/Link';
import Layout from '@theme/Layout';
import styles from './index.module.css';

interface FeatureCardProps {
  href: string;
  icon: string;
  title: string;
  body: string;
  cta: string;
}

function FeatureCard({ href, icon, title, body, cta }: FeatureCardProps) {
  return (
    <Link to={href} className={styles.featureCard}>
      <span className={styles.featureIcon} aria-hidden>{icon}</span>
      <h3 className={styles.featureTitle}>{title}</h3>
      <p className={styles.featureBody}>{body}</p>
      <span className={styles.featureFooter}>{cta} ↘</span>
    </Link>
  );
}

export default function Home(): React.ReactElement {
  return (
    <Layout
      title="Byte8 VAT Validator — Magento 2 EU/UK/CH B2B validation"
      description="EU VIES + UK HMRC + Swiss UID validation for Magento 2. Auto-applies the right tax rule at checkout. DACH-ready audit log."
    >
      <main>
        {/* Hero */}
        <section className={styles.heroSection}>
          <div className={styles.heroContent}>
            <span className={styles.eyebrow}>Magento 2 · Free · MIT</span>
            <h1 className={styles.heroTitle}>
              VAT validation that{' '}
              <span className={styles.heroTitleAccent}>actually works</span>{' '}
              in 2026.
            </h1>
            <p className={styles.heroSubtitle}>
              EU VIES + UK HMRC + Swiss UID-Register, hit at registration AND checkout.
              Auto-applies the right customer group so your zero-tax rule fires before
              the order is placed. DACH-ready 10-year audit log built in.
            </p>
            <div className={styles.heroCtas}>
              <Link className="button button--primary button--lg" to="/docs/getting-started/quick-start">
                Get started in 5 minutes
              </Link>
              <Link className="button button--secondary button--lg" to="/docs/intro">
                Read the docs
              </Link>
            </div>

            <div className={styles.statsRow}>
              <div className={styles.stat}>
                <span className={styles.statValue}>27 + 1 + 1</span>
                <span className={styles.statLabel}>EU states + UK + Switzerland</span>
              </div>
              <div className={styles.stat}>
                <span className={styles.statValue}>0</span>
                <span className={styles.statLabel}>SOAP / OAuth dependencies</span>
              </div>
              <div className={styles.stat}>
                <span className={styles.statValue}>10y</span>
                <span className={styles.statLabel}>§147 AO retention default</span>
              </div>
              <div className={styles.stat}>
                <span className={styles.statValue}>MIT</span>
                <span className={styles.statLabel}>License — free forever</span>
              </div>
            </div>
          </div>
        </section>

        {/* Core capabilities */}
        <section className={styles.section}>
          <header className={styles.sectionHeader}>
            <span className={styles.sectionEyebrow}>Core</span>
            <p className={styles.sectionLead}>
              Three upstreams. One unified result. Pluggable per region.
            </p>
          </header>

          <div className={styles.cardGrid}>
            <FeatureCard
              href="/docs/clients/vies"
              icon="🇪🇺"
              title="EU VIES (REST)"
              body="All 27 EU member states + Northern Ireland (XI). Uses the current REST endpoint — no SOAP, no ext-soap. Captures the requestIdentifier for §18 UStG qualified confirmation."
              cta="VIES client"
            />
            <FeatureCard
              href="/docs/clients/hmrc"
              icon="🇬🇧"
              title="UK HMRC lookup"
              body="The public unauthenticated HMRC endpoint — no OAuth, no client registration. Returns company name + address from the official HMRC database."
              cta="HMRC client"
            />
            <FeatureCard
              href="/docs/clients/uid-che"
              icon="🇨🇭"
              title="Swiss UID-Register"
              body="Bundesamt für Statistik integration. Returns valid only when the organisation is active AND VAT-registered (MWST) — matching what an EU/UK merchant means by 'valid Swiss B2B counterparty'."
              cta="UID-CHE client"
            />
          </div>
        </section>

        {/* DACH callout */}
        <section className={styles.section}>
          <header className={styles.sectionHeader}>
            <span className={styles.sectionEyebrow}>DACH compliance</span>
            <p className={styles.sectionLead}>
              Built for German Finanzamt audits — useful for any merchant.
            </p>
          </header>

          <div className={styles.cardGrid}>
            <FeatureCard
              href="/docs/validation-log/overview"
              icon="📒"
              title="DB-backed validation log"
              body="Every valid / invalid outcome persisted to byte8_vat_validator_log with raw upstream payloads. Ten-year default retention matches §147 AO. Transient errors are not logged."
              cta="Validation log"
            />
            <FeatureCard
              href="/docs/validation-log/csv-export"
              icon="📤"
              title="CSV / Excel export"
              body="One-click export from the admin grid. Separate ACL resource so you can grant view access without granting export — tighter than most competitor extensions."
              cta="Export workflow"
            />
            <FeatureCard
              href="/docs/advanced/privacy-gdpr"
              icon="🛡️"
              title="GDPR / privacy"
              body="Lawful basis: Art. 6(1)(c) tax-law obligation, not consent. We provide suggested privacy-policy copy and a worked example of how erasure interacts with §147 AO retention."
              cta="Privacy & GDPR"
            />
          </div>
        </section>

        {/* Front-end */}
        <section className={styles.section}>
          <header className={styles.sectionHeader}>
            <span className={styles.sectionEyebrow}>Front-end</span>
            <p className={styles.sectionLead}>
              Live in-form validation across every Magento storefront family.
            </p>
          </header>

          <div className={styles.cardGrid}>
            <FeatureCard
              href="/docs/frontend/hyva"
              icon="⚡"
              title="Hyvä companion"
              body="Alpine + Tailwind indicator under the registration form. No extra JS dependencies — both already ship with Hyvä. Drops in via layout XML."
              cta="Hyvä module"
            />
            <FeatureCard
              href="/docs/frontend/velafront"
              icon="▲"
              title="VelaFront / Next.js"
              body="React hook + drop-in <VatInput> component. AbortController-cancelled in-flight requests, 600ms debounce. Mirrors the server normaliser to stay consistent."
              cta="VelaFront widget"
            />
            <FeatureCard
              href="/docs/advanced/rest-api"
              icon="🔌"
              title="Anonymous REST endpoint"
              body="GET /V1/byte8-vat-validator/validate/:cc/:vn — call from any frontend, headless or otherwise. Same code path the live observers use."
              cta="REST API"
            />
          </div>
        </section>

        {/* CTA band */}
        <section className={styles.ctaBand}>
          <h2 className={styles.ctaTitle}>Five minutes to running.</h2>
          <p className={styles.ctaSubtitle}>
            Composer install, one config flag, and a CLI smoke test. No external account needed.
          </p>
          <div className={styles.heroCtas}>
            <Link className="button button--primary button--lg" to="/docs/getting-started/quick-start">
              Quick start
            </Link>
            <Link className="button button--secondary button--lg" to="https://github.com/byte8/module-vat-validator">
              View on GitHub
            </Link>
          </div>
        </section>
      </main>
    </Layout>
  );
}
