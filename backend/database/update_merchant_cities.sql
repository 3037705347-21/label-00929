-- 更新商家城市数据（测试用）
-- 为现有商家随机分配城市

SET @cities = '北京,上海,广州,深圳,杭州,成都,重庆,武汉,西安,南京';

-- 更新所有城市为空的商家
UPDATE merchants 
SET city = ELT(FLOOR(1 + RAND() * 10), '北京', '上海', '广州', '深圳', '杭州', '成都', '重庆', '武汉', '西安', '南京')
WHERE city IS NULL OR city = '';

-- 查看更新结果
SELECT id, shop_name, city FROM merchants;
