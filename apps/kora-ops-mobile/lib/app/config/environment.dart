/// Environnements d'exécution supportés par l'application.
enum Environment { dev, staging, prod }

extension EnvironmentX on Environment {
  String get name => switch (this) {
        Environment.dev => 'dev',
        Environment.staging => 'staging',
        Environment.prod => 'prod',
      };

  static Environment fromString(String value) => switch (value.toLowerCase()) {
        'staging' => Environment.staging,
        'prod' || 'production' => Environment.prod,
        _ => Environment.dev,
      };
}
