/// Réponse paginée standardisée, compatible avec le format de pagination
/// Laravel (`data`, `meta.current_page`, `meta.last_page`, `meta.total`)
/// utilisé par l'API KORA (kora-ops-web).
///
/// ```dart
/// final response = await dio.get('/bus/bookings');
/// final page = PaginatedResponse.fromJson(
///   response.data as Map<String, dynamic>,
///   (json) => BookingModel.fromJson(json as Map<String, dynamic>),
/// );
/// ```
class PaginatedResponse<T> {
  const PaginatedResponse({
    required this.items,
    required this.page,
    required this.pageSize,
    required this.totalItems,
    required this.totalPages,
  });

  factory PaginatedResponse.fromJson(
    Map<String, dynamic> json,
    T Function(Object? json) fromJsonT,
  ) {
    final rawItems = json['data'];
    final items = rawItems is List ? rawItems.map(fromJsonT).toList() : <T>[];

    final meta = json['meta'];
    final metaMap = meta is Map<String, dynamic>
        ? meta
        : const <String, dynamic>{};

    return PaginatedResponse<T>(
      items: items,
      page: (metaMap['current_page'] as num?)?.toInt() ?? 1,
      pageSize: (metaMap['per_page'] as num?)?.toInt() ?? items.length,
      totalItems: (metaMap['total'] as num?)?.toInt() ?? items.length,
      totalPages: (metaMap['last_page'] as num?)?.toInt() ?? 1,
    );
  }

  final List<T> items;
  final int page;
  final int pageSize;
  final int totalItems;
  final int totalPages;

  bool get hasNextPage => page < totalPages;
  bool get isEmpty => items.isEmpty;
  bool get isNotEmpty => items.isNotEmpty;
}
