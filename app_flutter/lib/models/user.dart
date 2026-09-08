class AppUser {
  final int id;
  final String username;
  final String fullName;
  final String email;
  final String? phone;
  final String avatar;
  final String bio;
  final String location;
  final String website;
  final String role;
  final String roleName;
  final List<String> permissions;
  final bool isVerified;
  final int postsCount;

  AppUser({
    required this.id,
    required this.username,
    required this.fullName,
    required this.email,
    this.phone,
    required this.avatar,
    this.bio = '',
    this.location = '',
    this.website = '',
    required this.role,
    this.roleName = '',
    this.permissions = const [],
    this.isVerified = false,
    this.postsCount = 0,
  });

  factory AppUser.fromJson(Map<String, dynamic> j) => AppUser(
        id: j['id'] ?? 0,
        username: j['username'] ?? '',
        fullName: j['full_name'] ?? '',
        email: j['email'] ?? '',
        phone: j['phone'],
        avatar: j['avatar'] ?? '',
        bio: j['bio'] ?? '',
        location: j['location'] ?? '',
        website: j['website'] ?? '',
        role: j['role'] ?? 'user',
        roleName: j['role_name'] ?? '',
        permissions: (j['permissions'] as List?)?.map((e) => '$e').toList() ?? const [],
        isVerified: j['is_verified'] == true,
        postsCount: j['posts_count'] ?? 0,
      );

  bool can(String perm) => permissions.contains('all') || permissions.contains(perm);
  bool get isAdmin => role == 'super_admin' || role == 'admin';
  bool get isManager => isAdmin || role == 'manager';
  bool get isHr => isAdmin || role == 'hr';
  bool get isEditorial =>
      isManager || role == 'editor' || role == 'reporter';
}
