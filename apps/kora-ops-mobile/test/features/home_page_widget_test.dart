import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:mocktail/mocktail.dart';
import 'package:kora_ops_mobile/features/auth/presentation/cubit/auth_cubit.dart';
import 'package:kora_ops_mobile/features/auth/domain/usecases/get_current_user.dart';
import 'package:kora_ops_mobile/features/auth/domain/usecases/login_user.dart';
import 'package:kora_ops_mobile/features/auth/domain/usecases/logout_user.dart';
import 'package:kora_ops_mobile/features/home/presentation/pages/home_page.dart';

class MockLoginUser extends Mock implements LoginUser {}

class MockLogoutUser extends Mock implements LogoutUser {}

class MockGetCurrentUser extends Mock implements GetCurrentUser {}

void main() {
  testWidgets('HomePage affiche les 4 tuiles opérationnelles', (WidgetTester tester) async {
    final AuthCubit cubit = AuthCubit(
      loginUser: MockLoginUser(),
      logoutUser: MockLogoutUser(),
      getCurrentUser: MockGetCurrentUser(),
    );

    await tester.pumpWidget(
      MaterialApp(
        home: BlocProvider<AuthCubit>.value(value: cubit, child: const HomePage()),
      ),
    );

    expect(find.text('Manifestes'), findsOneWidget);
    expect(find.text('Embarquement'), findsOneWidget);
    expect(find.text('Impression'), findsOneWidget);
    expect(find.text('Synchronisation'), findsOneWidget);
  });
}
