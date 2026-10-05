# symfony-remote-payment-stripe

Version: 2.0.1

## Configuration

```yaml
# config/packages/wexample_symfony_remote_payment_stripe.yaml
wexample_symfony_remote_payment_stripe:
  secret_key: '%env(default::STRIPE_SECRET_KEY)%'
  webhook_secret: '%env(default::STRIPE_WEBHOOK_SECRET)%'   # of the endpoint /_payment/webhook/stripe
  methods: [card, sepa_debit, bancontact]
```

Point a Stripe webhook endpoint at `/_payment/webhook/stripe` with the `payment_intent.*` events. Without a secret key the provider reads Unconfigured and refuses calls.

Balance transactions are read oldest first, with their payment (PaymentIntent) when the source is a charge, so accounting matches them with the invoices paid online.

## Table of Contents

- [Configuration](#configuration)
- [Integration in the Suite](#integration-in-the-suite)
- [Dependencies](#dependencies)
- [Versioning & Compatibility Policy](#versioning--compatibility-policy)
- [License](#license)
- [About us](#about-us)
- [Migration Notes](#migration-notes)

## Integration in the Suite

This package is part of the Wexample Suite — a collection of high-quality, modular tools designed to work seamlessly together across multiple languages and environments.

### Related Packages

The suite includes packages for configuration management, file handling, prompts, and more. Each package can be used independently or as part of the integrated suite.

Visit the [Wexample Suite documentation](https://docs.wexample.com) for the complete package ecosystem.

## Dependencies

- php: >=8.5
- stripe/stripe-php: ^17.0 || ^18.0 || ^19.0
- wexample/symfony-helpers: >=14.0.0
- wexample/symfony-remote: >=2.0.0
- wexample/symfony-remote-payment: >=2.0.0

## Versioning & Compatibility Policy

Wexample packages follow **Semantic Versioning** (SemVer):

- **MAJOR**: Breaking changes
- **MINOR**: New features, backward compatible
- **PATCH**: Bug fixes, backward compatible

We maintain backward compatibility within major versions and provide clear migration guides for breaking changes.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

Free to use in both personal and commercial projects.

## About us

[Wexample](https://wexample.com) stands as a cornerstone of the digital ecosystem — a collective of seasoned engineers, researchers, and creators driven by a relentless pursuit of technological excellence. More than a media platform, it has grown into a vibrant community where innovation meets craftsmanship, and where every line of code reflects a commitment to clarity, durability, and shared intelligence.

This packages suite embodies this spirit. Trusted by professionals and enthusiasts alike, it delivers a consistent, high-quality foundation for modern development — open, elegant, and battle-tested. Its reputation is built on years of collaboration, refinement, and rigorous attention to detail, making it a natural choice for those who demand both robustness and beauty in their tools.

Wexample cultivates a culture of mastery. Each package, each contribution carries the mark of a community that values precision, ethics, and innovation — a community proud to shape the future of digital craftsmanship.

## Migration Notes

When upgrading between major versions, refer to the migration guides in the documentation.

Breaking changes are clearly documented with upgrade paths and examples.
