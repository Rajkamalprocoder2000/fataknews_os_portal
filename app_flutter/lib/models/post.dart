class PostRow {
  final int id;
  final String title;
  final String slug;
  final String excerpt;
  final String status;
  final String type;
  final String location;
  final String? thumbnailUrl;
  final String? videoUrl;
  final int views;
  final int likes;
  final int comments;
  final bool featured;
  final bool breaking;
  final String? author;
  final String? categoryName;
  final String? createdAt;

  PostRow.fromJson(Map<String, dynamic> j)
      : id = j['id'] ?? 0,
        title = j['title'] ?? '',
        slug = j['slug'] ?? '',
        excerpt = j['excerpt'] ?? '',
        status = j['status'] ?? 'draft',
        type = j['type'] ?? 'news',
        location = j['location'] ?? '',
        thumbnailUrl = j['thumbnail_url'],
        videoUrl = j['video_url'],
        views = j['views_count'] ?? 0,
        likes = j['likes_count'] ?? 0,
        comments = j['comments_count'] ?? 0,
        featured = j['is_featured'] == 1 || j['is_featured'] == true,
        breaking = j['is_breaking'] == 1 || j['is_breaking'] == true,
        author = j['author'],
        categoryName = j['category_name'],
        createdAt = j['created_at'];
}
