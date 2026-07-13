-- 1. Top 10 active users in the last 7 days (interaction activity).
SELECT u.id, u.name, u.email, COUNT(i.id) AS interaction_count
FROM interactions AS i
JOIN users AS u ON u.id = i.user_id
WHERE i.created_at >= NOW() - INTERVAL 7 DAY
GROUP BY u.id, u.name, u.email
ORDER BY interaction_count DESC, u.id ASC
LIMIT 10;

-- 2. Set :user_id. Find authors whose posts the user engaged with most in 30 days,
-- then return their recent posts. The CTE avoids repeated correlated subqueries.
WITH engaged_authors AS (
  SELECT p.user_id AS author_id, COUNT(*) AS engagement_count
  FROM interactions AS i
  JOIN posts AS p ON p.id = i.post_id
  WHERE i.user_id = :user_id AND i.created_at >= NOW() - INTERVAL 30 DAY
  GROUP BY p.user_id
)
SELECT p.*, ea.engagement_count
FROM posts AS p
JOIN engaged_authors AS ea ON ea.author_id = p.user_id
ORDER BY ea.engagement_count DESC, p.created_at DESC;

-- 3. Posts viewed more than 100 times and never reacted to.
SELECT p.id, p.user_id, p.content, COUNT(i.id) AS view_count
FROM posts AS p
JOIN interactions AS i ON i.post_id = p.id AND i.type = 'view'
LEFT JOIN interactions AS r ON r.post_id = p.id AND r.type = 'reaction'
GROUP BY p.id, p.user_id, p.content
HAVING COUNT(i.id) > 100 AND COUNT(r.id) = 0
ORDER BY view_count DESC;

-- 4. Users who created more than 20 posts in the last 24 hours.
SELECT u.id, u.name, u.email, COUNT(p.id) AS post_count
FROM users AS u
JOIN posts AS p ON p.user_id = u.id
WHERE p.created_at >= NOW() - INTERVAL 24 HOUR
GROUP BY u.id, u.name, u.email
HAVING COUNT(p.id) > 20
ORDER BY post_count DESC;
