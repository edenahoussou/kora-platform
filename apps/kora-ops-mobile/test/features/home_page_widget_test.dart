import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/app/theme/theme_cubit.dart';
import 'package:kora_ops_mobile/features/auth/domain/usecases/get_current_user.dart';
import 'package:kora_ops_mobile/features/auth/domain/usecases/get_saved_email.dart';
import 'package:kora_ops_mobile/features/auth/domain/usecases/logout_user.dart';
import 'package:kora_ops_mobile/features/auth/presentation/cubit/auth_cubit.dart';
import 'package:kora_ops_mobile/features/home/presentation/pages/home_page.dart';
import 'package:mocktail/mocktail.dart';
import 'package:shared_preferences/shared_preferences.dart';

class MockGetCurrentUser extends Mock implements GetCurrentUser {}

class MockGetSavedEmail extends Mock implements GetSavedEmail {}

class MockLogoutUser extends Mock implements LogoutUser {}

void main() {
  testWidgets('HomePage affiche les 4 tuiles opérationnelles', (WidgetTester tester) async {
    final AuthCubit authCubit = AuthCubit(
      getCurrentUser: MockGetCurrentUser(),
      getSavedEmail: MockGetSavedEmail(),
      logoutUser: MockLogoutUser(),
    );
    SharedPreferences.setMockInitialValues(<String, Object>{});
    final ThemeCubit themeCubit = ThemeCubit(await SharedPreferences.getInstance());

    await tester.pumpWidget(
      MaterialApp(
        home: MultiBlocProvider(
          providers: <BlocProvider<Object?>>[
            BlocProvider<AuthCubit>.value(value: authCubit),
            BlocProvider<ThemeCubit>.value(value: themeCubit),
          ],
          child: const HomePage(),
        ),
      ),
    );

    expect(find.text('Manifestes'), findsOneWidget);
    expect(find.text('Embarquement'), findsOneWidget);
    expect(find.text('Impression'), findsOneWidget);
    expect(find.text('Synchronisation'), findsOneWidget);
  });
}
