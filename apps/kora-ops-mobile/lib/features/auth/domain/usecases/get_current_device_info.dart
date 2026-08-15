import '../../../../core/result/result.dart';
import '../entities/device_info.dart';
import '../repositories/auth_repository.dart';

class GetCurrentDeviceInfo {
  const GetCurrentDeviceInfo(this._repository);

  final AuthRepository _repository;

  Future<Result<DeviceInfo>> call() => _repository.getCurrentDeviceInfo();
}
