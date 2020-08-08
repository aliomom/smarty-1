import 'package:inject/inject.dart';
import 'package:shared_preferences/shared_preferences.dart';

@singleton
@provide
class SharedPreferencesHelper {
  static const String _KEY_TOKEN = "token";
  static const String _KEY_PASSWORD = 'password';
  static const String _KEY_EMAIL = 'email';

  SharedPreferences _sharedPreferences;

  SharedPreferencesHelper() {
    SharedPreferences.getInstance().then((value) {
      _sharedPreferences = value;
    });
  }

  Future<void> setToken(String token) async {
    await _sharedPreferences.setString(_KEY_TOKEN, token);
  }

  Future<String> getToken() async {
    return _sharedPreferences.getString(_KEY_TOKEN);
  }

  Future<void> setUserEmail(String email) async {
    return _sharedPreferences.setString(_KEY_EMAIL, email);
  }

  Future<void> setUserPassword(String password) async {
    return _sharedPreferences.setString(_KEY_PASSWORD, password);
  }

  Future<String> getUserEmail() async {
    return _sharedPreferences.getString(_KEY_EMAIL);
  }

  Future<String> getUserPassword() async {
    return _sharedPreferences.getString(_KEY_PASSWORD);
  }
}
