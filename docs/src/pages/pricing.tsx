import Link from '@docusaurus/Link';
import Layout from '@theme/Layout';
import styles from './index.module.css';

export default function Pricing(): React.ReactElement {
  return (
    <Layout
      title="Pricing — Byte8 VAT Validator"
      description="Free forever. The module is the lead magnet; Byte8 Ledger is the revenue product."
    >
      <main>
        <section className={styles.heroSection}>
          <div className={styles.heroContent}>
            <span className={styles.eyebrow}>Pricing</span>
            <h1 className={styles.heroTitle}>
              Free. <span className={styles.heroTitleAccent}>Forever.</span>
            </h1>
            <p className={styles.heroSubtitle}>
              Byte8 VAT Validator is MIT-licensed and free on the Magento Marketplace
              + here on GitHub. Our paying product is{' '}
              <Link to="https://byte8.io/ledger">Byte8 Ledger</Link> — the
              Sage / Xero / FreeAgent sync. If you grow into needing that, great.
              If not, keep using the validator forever — no expiring trial, no
              feature gating, no upsell tricks.
            </p>
            <div className={styles.heroCtas}>
              <Link className="button button--primary button--lg" to="/docs/getting-started/quick-start">
                Install it
              </Link>
              <Link className="button button--secondary button--lg" to="https://byte8.io/ledger">
                See Byte8 Ledger
              </Link>
            </div>
          </div>
        </section>
      </main>
    </Layout>
  );
}
