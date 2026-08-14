import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/app/theme/theme_cubit.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  setUp(() => SharedPreferences.setMockInitialValues(<String, Object>{}));

  test('démarre en ThemeMode.system par défaut', () async {
    final ThemeCubit cubit = ThemeCubit(await SharedPreferences.getInstance());
    expect(cubit.state, ThemeMode.system);
  });

  test('cycle() fait défiler system -> light -> dark -> system', () async {
    final ThemeCubit cubit = ThemeCubit(await SharedPreferences.getInstance());

    await cubit.cycle();
    expect(cubit.state, ThemeMode.light);

    await cubit.cycle();
    expect(cubit.state, ThemeMode.dark);

    await cubit.cycle();
    expect(cubit.state, ThemeMode.system);
  });

  test('le choix manuel est persisté et relu au prochain lancement', () async {
    SharedPreferences.setMockInitialValues(<String, Object>{'app.theme_mode': 'dark'});
    final ThemeCubit cubit = ThemeCubit(await SharedPreferences.getInstance());
    expect(cubit.state, ThemeMode.dark);
  });

  test('setLight()/setDark()/setSystem() émettent le mode attendu', () async {
    final ThemeCubit cubit = ThemeCubit(await SharedPreferences.getInstance());

    await cubit.setDark();
    expect(cubit.state, ThemeMode.dark);

    await cubit.setLight();
    expect(cubit.state, ThemeMode.light);

    await cubit.setSystem();
    expect(cubit.state, ThemeMode.system);
  });
}
